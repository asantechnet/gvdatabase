<?php

/**
 * Academy route tree.
 *
 * Each feature owns one file under routes/academy/. Adding attendance or
 * certificates must not require editing routes/api.php again.
 */
foreach (glob(__DIR__.'/academy/*.php') as $file) {
    require $file;
}
