<?php
declare(strict_types=1);

/**
 * Снимки страницы: то, что стоит за кнопкой «Отменить».
 *
 * Перед каждой правкой блоки страницы складываются сюда целиком. Копирование
 * блоков из основного языка затирает перевод одной кнопкой, и без снимка это
 * был бы путь в один конец.
 *
 * Храним по десять последних на страницу и язык: глубже никто не отменяет,
 * а место на shared-хостинге не бесконечное.
 */
final class PageRevisions
{
    /** Сколько снимков храним на языковую версию: дальше они только копятся. */
    private const KEEP_REVISIONS = 15;

    public function __construct(private Db $db)
    {
    }

    /**
     * Снимок языковой версии перед изменением — чтобы правку можно было отменить.
     *
     * Снимаем до действия, а не после: отменять нужно то, что было, а не то,
     * что получилось.
     */
    public function snapshot(int $pageId, string $locale, ?int $authorId, string $comment = ''): int
    {
        $snapshot = [
            // Настройки языковой версии: заголовок, адрес, описание, панель
            'locale' => $this->db->first(
                'SELECT * FROM page_locales WHERE page_id = :id AND locale = :locale',
                ['id' => $pageId, 'locale' => $locale]
            ),
            'blocks' => $this->db->all(
                'SELECT sort, type, data_json, is_visible FROM page_blocks
                  WHERE page_id = :id AND locale = :locale ORDER BY sort, id',
                ['id' => $pageId, 'locale' => $locale]
            ),
        ];

        $id = $this->db->insert('page_revisions', [
            'page_id'       => $pageId,
            'locale'        => $locale,
            'author_id'     => $authorId,
            'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'comment'       => mb_substr($comment, 0, 255),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        $this->trimRevisions($pageId, $locale);

        return $id;
    }

    /** Последний снимок языковой версии — то, к чему вернёт «Отменить». */
    public function lastRevision(int $pageId, string $locale): ?array
    {
        return $this->db->first(
            'SELECT * FROM page_revisions
              WHERE page_id = :id AND locale = :locale
              ORDER BY id DESC LIMIT 1',
            ['id' => $pageId, 'locale' => $locale]
        );
    }

    /**
     * Возвращает языковую версию к снимку.
     *
     * Сам снимок удаляем: отмена одноразовая, иначе повторное нажатие
     * возвращало бы к тому же состоянию и выглядело сломанным.
     */
    public function restoreRevision(int $revisionId): bool
    {
        $revision = $this->db->first('SELECT * FROM page_revisions WHERE id = :id', ['id' => $revisionId]);

        if ($revision === null) {
            return false;
        }

        $snapshot = json_decode((string) $revision['snapshot_json'], true);

        if (!is_array($snapshot)) {
            return false;
        }

        $pageId = (int) $revision['page_id'];
        $locale = (string) $revision['locale'];

        $this->db->transaction(function () use ($pageId, $locale, $snapshot, $revisionId): void {
            $this->db->delete('page_blocks', 'page_id = :id AND locale = :locale', ['id' => $pageId, 'locale' => $locale]);

            foreach ($snapshot['blocks'] ?? [] as $block) {
                $this->db->insert('page_blocks', [
                    'page_id'    => $pageId,
                    'locale'     => $locale,
                    'sort'       => (int) $block['sort'],
                    'type'       => (string) $block['type'],
                    'data_json'  => (string) $block['data_json'],
                    'is_visible' => (int) $block['is_visible'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Заголовок, адрес и панель тоже возвращаем: их меняют тем же действием
            $row = $snapshot['locale'] ?? null;

            if (is_array($row)) {
                $this->db->update('page_locales', [
                    'slug'        => (string) ($row['slug'] ?? ''),
                    'title'       => (string) ($row['title'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'og_image'    => (string) ($row['og_image'] ?? ''),
                    'bar_json'    => $row['bar_json'] ?? null,
                    'updated_at'  => date('Y-m-d H:i:s'),
                ], 'page_id = :id AND locale = :locale', ['id' => $pageId, 'locale' => $locale]);
            }

            $this->db->delete('page_revisions', 'id = :id', ['id' => $revisionId]);
        });

        return true;
    }

    private function trimRevisions(int $pageId, string $locale): void
    {
        $ids = $this->db->all(
            'SELECT id FROM page_revisions
              WHERE page_id = :id AND locale = :locale
              ORDER BY id DESC',
            ['id' => $pageId, 'locale' => $locale]
        );

        foreach (array_slice($ids, self::KEEP_REVISIONS) as $row) {
            $this->db->delete('page_revisions', 'id = :id', ['id' => (int) $row['id']]);
        }
    }
}
