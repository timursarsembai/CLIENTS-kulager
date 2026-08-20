<?php
declare(strict_types=1);

/**
 * Медиатека: загрузка файлов и описания к ним.
 *
 * Отдельно есть выдача списка в JSON — её просит окно выбора картинки,
 * общее для админки и режима правки на странице.
 */
final class AdminMedia extends AdminSection
{
    public function mediaRoutes(array $segments): void
    {
        $library = new MediaLibrary($this->db);

        // media/{id}/delete и media/{id}/alt
        if (ctype_digit((string) ($segments[0] ?? ''))) {
            $item = $library->find((int) $segments[0]);

            if ($item === null) {
                $this->notFound();

                return;
            }

            match ($segments[1] ?? '') {
                'delete' => $this->mediaDelete($library, $item),
                'alt'    => $this->mediaAlt($library, $item),
                default  => $this->notFound(),
            };

            return;
        }

        match ($segments[0] ?? '') {
            ''       => $this->mediaList($library),
            'upload' => $this->mediaUpload($library),
            'json'   => $this->mediaJson($library),
            default  => $this->notFound(),
        };
    }

    private function mediaList(MediaLibrary $library): void
    {
        $this->render('media', [
            'items'    => $library->all(),
            'writable' => $library->isWritable(),
            'gd'       => extension_loaded('gd'),
            'limit'    => ini_get('upload_max_filesize'),
        ], 'Медиатека');
    }

    private function mediaJson(MediaLibrary $library): void
    {
        $items = [];

        foreach ($library->all(500) as $item) {
            $items[] = [
                'id'     => (int) $item['id'],
                'path'   => (string) $item['path'],
                'name'   => basename((string) $item['path']),
                'alt'    => (string) $item['alt_ru'],
                'width'  => (int) $item['width'],
                'height' => (int) $item['height'],
            ];
        }

        $this->json(['items' => $items, 'writable' => $library->isWritable()]);
    }

    private function mediaUpload(MediaLibrary $library): void
    {
        if (!$this->isPost()) {
            $this->redirect('media');

            return;
        }

        $uploaded = 0;
        $errors = [];
        $added = [];

        // Файлы приходят пачкой: раскладываем массив $_FILES по одному
        $files = $_FILES['files'] ?? null;

        foreach ((array) ($files['name'] ?? []) as $index => $name) {
            [$item, $error] = $library->upload([
                'name'     => $name,
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$index] ?? 0,
            ]);

            if ($item !== null) {
                $uploaded++;
                $added[] = [
                    'path' => (string) $item['path'],
                    'name' => basename((string) $item['path']),
                ];
                continue;
            }

            if ($error !== null) {
                $errors[] = $name . ': ' . $error;
            }
        }

        if ($uploaded > 0) {
            $this->auth->log('media_upload', (string) $uploaded);
        }

        // Загрузка из окна выбора картинки: страница не перезагружается,
        // поэтому отвечаем списком добавленных файлов
        if (($_POST['json'] ?? '') === '1') {
            $this->json(['uploaded' => $added, 'errors' => $errors]);

            return;
        }

        if ($uploaded > 0) {
            $this->flash(at('Загружено файлов: %d.', $uploaded));
        }

        foreach (array_slice($errors, 0, 5) as $message) {
            $this->flash($message);
        }

        $this->redirect('media');
    }

    private function mediaAlt(MediaLibrary $library, array $item): void
    {
        if ($this->isPost()) {
            $library->updateAlt(
                (int) $item['id'],
                (string) ($_POST['alt_ru'] ?? ''),
                (string) ($_POST['alt_kk'] ?? '')
            );
            $this->flash(at('Описание сохранено.'));
        }

        $this->redirect('media');
    }

    private function mediaDelete(MediaLibrary $library, array $item): void
    {
        if (!$this->isPost()) {
            $this->redirect('media');

            return;
        }

        // Файл может использоваться на страницах — предупреждаем, но не запрещаем
        $used = (int) $this->db->value(
            'SELECT COUNT(*) FROM page_blocks WHERE data_json LIKE :needle',
            ['needle' => '%' . $item['path'] . '%'],
            0
        );

        $library->delete((int) $item['id']);
        $this->auth->log('media_delete', (string) $item['path']);

        $this->flash($used > 0
            ? at('Файл удалён, но он использовался в блоках (%d) — проверьте страницы.', $used)
            : at('Файл удалён.'));

        $this->redirect('media');
    }

    /* ----------------------------------------------------- редактор страницы */
}
