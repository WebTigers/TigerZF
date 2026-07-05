<?php
/**
 * Zend_Log_Writer_ErrorLog — emit through PHP's native error_log().
 *
 * TigerZF addition. The zero-dependency, universally-correct sink: we deliberately
 * reuse PHP's transport instead of opening our own file or stream, because every
 * cloud platform's log agent already collects it —
 *
 *   - AWS: PHP-FPM's error_log → the CloudWatch agent tails it into a log group.
 *   - GCP: the Ops Agent collects the same file / stdout into Cloud Logging.
 *   - Azure: the Diagnostics/Container agent ships stdout to Log Analytics.
 *
 * So "log to error_log() and let the platform ship it" is the sane default on all
 * three — no SDK, no new file, no permission juggling, no interleave race with
 * PHP-FPM's own writer. The JSON one-object-per-line shape is supplied by
 * Zend_Log_Formatter_Json (the default formatter set below).
 *
 * @category   Zend
 * @package    Zend_Log
 * @subpackage Writer
 */
class Zend_Log_Writer_ErrorLog extends Zend_Log_Writer_Abstract
{
    public function __construct()
    {
        $this->_formatter = new Zend_Log_Formatter_Json();
    }

    /**
     * @param array $event
     */
    protected function _write($event)
    {
        // error_log() appends its own newline, so trim the formatter's.
        error_log(rtrim($this->_formatter->format($event), "\r\n"));
    }

    /**
     * @param  array|Zend_Config $config
     * @return Zend_Log_Writer_ErrorLog
     */
    public static function factory($config)
    {
        return new self();
    }
}
