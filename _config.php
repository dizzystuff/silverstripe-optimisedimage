<?php

define('OPTIMISEDIMAGE_DIR', basename(__DIR__));
define('OPTIMISEDIMAGE_PATH', __DIR__);

Image::set_backend('OptimisedGDBackend');