<?php
/**
 * Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Product/support https://builtnotbegged.com. SPDX-License-Identifier: GPL-2.0-or-later.
 */

defined('ABSPATH') || exit;

final class CreditSoft_Financial_Tools_Sitemap extends WP_Sitemaps_Provider
{
    public function __construct()
    {
        $this->name = 'creditsofttools';
        $this->object_type = 'creditsofttools';
    }

    public function get_url_list($page_num, $object_subtype = '')
    {
        $page = (int) $page_num;
        if ($object_subtype !== '' || $page < 1) {
            return [];
        }

        $entries = CreditSoft_Financial_Tools::sitemapUrls();
        $limit = $this->pageSize();
        if ($page > (int) ceil(count($entries) / $limit)) {
            return [];
        }
        return array_slice($entries, ($page - 1) * $limit, $limit);
    }

    public function get_max_num_pages($object_subtype = '')
    {
        return $object_subtype === '' ? (int) ceil(count(CreditSoft_Financial_Tools::sitemapUrls()) / $this->pageSize()) : 0;
    }

    private function pageSize(): int
    {
        return max(1, (int) wp_sitemaps_get_max_urls($this->object_type));
    }
}
