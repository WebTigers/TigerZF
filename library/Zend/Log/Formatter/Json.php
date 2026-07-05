<?php
/**
 * Zend_Log_Formatter_Json — one JSON object per log line.
 *
 * TigerZF addition (not in upstream ZF1, which ships only Simple + Xml). Emits the
 * structured shape every modern log pipeline wants — AWS CloudWatch Logs Insights,
 * GCP Logs Explorer, Azure Log Analytics / Kusto — so queries key off $.level,
 * $.channel and any $.context.* field instead of tokenizing freeform strings.
 *
 * Output (single line, no pretty-print):
 *   {"ts":"2026-07-05T14:22:01-04:00","level":"ERR","channel":"app",
 *    "msg":"Auth login failed","context":{"request_id":"…"}}
 *
 * @category   Zend
 * @package    Zend_Log
 * @subpackage Formatter
 */
class Zend_Log_Formatter_Json implements Zend_Log_Formatter_Interface
{
    /**
     * @param  array $event  packed by Zend_Log: message/priority/priorityName/
     *                       timestamp, plus optional 'context' and 'channel' extras.
     * @return string        a single JSON line terminated with PHP_EOL
     */
    public function format($event)
    {
        $line = array(
            'ts'      => isset($event['timestamp'])    ? $event['timestamp']    : date('c'),
            'level'   => isset($event['priorityName']) ? $event['priorityName'] : 'UNKNOWN',
            'channel' => isset($event['channel'])      ? $event['channel']      : 'app',
            'msg'     => isset($event['message'])      ? (string) $event['message'] : '',
        );

        if (!empty($event['context']) && is_array($event['context'])) {
            $line['context'] = $event['context'];
        }

        // JSON_PARTIAL_OUTPUT_ON_ERROR: an un-encodable value (e.g. a non-UTF8 byte
        // string in context) must never turn a log call into a hard failure.
        $json = json_encode(
            $line,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($json === false) {
            unset($line['context']);   // last-ditch: drop context, keep the core line
            $json = json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $json . PHP_EOL;
    }

    /**
     * @param  array|Zend_Config $config
     * @return Zend_Log_Formatter_Json
     */
    public static function factory($config = array())
    {
        return new self();
    }
}
