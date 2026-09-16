<?php
/**
 * Opt-in assets for FlowMotion.
 *
 * Usage from a module directory: flow_motion_assets('../');
 * Usage from the project root:  flow_motion_assets();
 */
if (!function_exists('flow_motion_assets')) {
    function flow_motion_assets(string $relativePrefix = ''): void
    {
        $asset = static function (string $path): string {
            return function_exists('asset_url') ? asset_url($path) : $path;
        };

        $base = rtrim($relativePrefix, '/');
        $path = static function (string $file) use ($base): string {
            return ($base !== '' ? $base . '/' : '') . $file;
        };
        ?>
        <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
        <script src="<?php echo htmlspecialchars($asset($path('script/motion/flow-motion-presets.js')), ENT_QUOTES, 'UTF-8'); ?>"></script>
        <script src="<?php echo htmlspecialchars($asset($path('script/motion/flow-motion.js')), ENT_QUOTES, 'UTF-8'); ?>"></script>
        <?php
    }
}
