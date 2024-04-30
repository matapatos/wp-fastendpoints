<?php

/**
 * Holds Class that removes repeating http status in $data.
 *
 * @since 0.9.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Wp\FastEndpoints\Helpers;

use WP_Error;

class WpError extends WP_Error
{
    /**
     * @param  int  $statusCode  HTTP error code
     * @param  string|array  $message  The error message
     * @param  array  $data  Additional data to be sent
     * @param  bool  $escap  If we desire to escape the error message. Default: true
     *
     * @since 0.9.0
     */
    public function __construct(int $statusCode, string|array $message, array $data = [], bool $escape = true)
    {
        if (is_array($message)) {
            $data['all_messages'] = $message;
        }
        $firstMessage = $this->getFirstErrorMessage($message);
        $data = array_merge(['status' => $statusCode], $data);
        $firstMessage = $escape ? esc_html__($firstMessage) : $firstMessage;
        parent::__construct($statusCode, $firstMessage, $data);
    }

    /**
     * Gets the first message from an array
     */
    protected function getFirstErrorMessage(string|array $message): string
    {
        if (! is_array($message)) {
            return $message;
        }

        if (count($message) == 0) {
            return 'No error description provided';
        }

        while (is_array($message)) {
            $message = reset($message);
        }

        if ($message === false) {
            return 'No error description provided';
        }

        return $message;
    }
}
