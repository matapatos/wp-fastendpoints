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
     * @since 0.9.0
     *
     * @param  string|array  $message  The error message.
     */
    public function __construct(int $statusCode, $message, array $data = [])
    {
        if (is_array($message)) {
            $data['all_messages'] = $message;
        }
        $firstMessage = $this->getFirstErrorMessage($message);
        $data = array_merge(['status' => $statusCode], $data);
        parent::__construct($statusCode, esc_html__($firstMessage), $data);
    }

    /**
     * Gets the first message from an array
     *
     * @param  string|array  $message
     */
    protected function getFirstErrorMessage($message): string
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
