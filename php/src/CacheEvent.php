<?php
declare(strict_types=1);

namespace Tds\Frontend\Contract;

/**
 * One "this content changed" notice, sent from a module to a public site's
 * page cache (see {@see SiteCache}).
 *
 * **It names CONTENT, never a URL.** A blog post lives at `/{slug}` in German
 * and `/en/{slug}` in English, and changing it also dates the index page, the
 * category page, the tag pages, the author page, every paginated archive page
 * and the feed — and the English archive routes are not even a prefix of the
 * German ones (`/kategorie/…` vs `/en/category/…`). Only the site knows its own
 * route table, so the site translates an event into paths. Teaching this API
 * three sites' URL schemes would be a fourth copy of a truth that already
 * exists, and the copies would drift the first time a route is renamed.
 *
 * The `type` values are a contract between a module and the sites that render
 * its content; they are deliberately open strings rather than an enum, because
 * a new extension must be able to introduce its own without a contract release.
 * The ones in use today: `block`, `legal`, `post`, `tool`, `catalog`.
 */
final class CacheEvent
{
    /**
     * @param string      $type What kind of content changed (`post`, `block`, …).
     * @param string|null $id   Which one — a slug, a section key, a tool id.
     *                          Null means "all of this type".
     * @param string|null $lang `de` / `en`, or null when the change is
     *                          language-agnostic (a tool being switched off
     *                          changes both trees).
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $id = null,
        public readonly ?string $lang = null,
    ) {
    }

    /** The wire shape. Null members are omitted, so "all" stays absent, not empty. */
    public function toArray(): array
    {
        $out = ['type' => $this->type];
        if ($this->id !== null && $this->id !== '') {
            $out['id'] = $this->id;
        }
        if ($this->lang !== null && $this->lang !== '') {
            $out['lang'] = $this->lang;
        }

        return $out;
    }
}
