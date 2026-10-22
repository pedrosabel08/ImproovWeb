<?php
require_once dirname(__DIR__) . '/config/page_metadata.php';
if (!defined('FLOW_PAGE_METADATA_RENDERED')) {
    define('FLOW_PAGE_METADATA_RENDERED', true);
    echo flow_metadata_html();
}
