<?php

/**
 * @file plugins/generic/publishToFacebook/classes/PostLog.php
 *
 * @class PostLog
 *
 * @brief Data object representing a Facebook post log entry.
 */

namespace APP\plugins\generic\publishToFacebook\classes;

use PKP\core\DataObject;

class PostLog extends DataObject
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
}
