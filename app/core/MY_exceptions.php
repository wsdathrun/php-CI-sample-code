<?php
/**
 * Created by PhpStorm.
 * User: philips
 * Date: 16/12/9
 * Time: 下午12:00
 */

class MY_Exceptions extends CI_Exceptions
{
    function show_error($heading, $message, $template = 'error_general', $status_code = 500)
    {
        log_message( 'debug', print_r( $message, TRUE ) );
        throw new Exception(is_array($message) ? $message[1] : $message, $status_code );
    }
}