<?php
/**
 * Zend_Log_Writer_Azuremonitor — Azure Monitor writer (Application Insights).
 *
 * TigerZF addition. Ships batched log lines to Azure Monitor as Application Insights
 * "trace" (MessageData) telemetry over HTTPS. Unlike the AWS/GCP writers this needs
 * NO SDK — it POSTs to the ingestion endpoint with ext/curl, so its only hard
 * requirement is an instrumentation key (from an App Insights connection string).
 *
 * NOTE: on Azure the zero-dependency path is still `stderr` + Container Insights /
 * the Diagnostics agent shipping stdout to Log Analytics. Use this direct writer
 * when there's no agent (e.g. App Service) and you want telemetry in App Insights.
 *
 * Config (tiger.log.azure.* — see core.ini):
 *   connection_string  (preferred)  the App Insights connection string
 *                                    ("InstrumentationKey=…;IngestionEndpoint=…")
 *   instrumentation_key (alt)        just the key (uses the global endpoint)
 *   role               (optional)    cloud role name (default: "tiger")
 *
 * @category   Zend
 * @package    Zend_Log
 * @subpackage Writer
 */
class Zend_Log_Writer_Azuremonitor extends Zend_Log_Writer_Buffered
{
    /** @var string */ protected $_iKey;
    /** @var string */ protected $_endpoint;
    /** @var string */ protected $_role;

    /** Zend_Log priority name -> App Insights severityLevel (0..4). */
    protected static $_severity = array(
        'DEBUG' => 0, 'INFO' => 1, 'NOTICE' => 1,
        'WARN'  => 2, 'ERR' => 3, 'CRIT' => 4, 'ALERT' => 4, 'EMERG' => 4,
    );

    /**
     * @param  array|Zend_Config|null $config
     * @throws Zend_Log_Exception when curl is missing or no instrumentation key is set
     */
    public function __construct($config = null)
    {
        if (!function_exists('curl_init')) {
            throw new Zend_Log_Exception('Zend_Log_Writer_Azuremonitor requires ext/curl.');
        }
        $c = self::_toArray($config);

        $iKey     = !empty($c['instrumentation_key']) ? (string) $c['instrumentation_key'] : '';
        $endpoint = 'https://dc.services.visualstudio.com';   // global default
        if (!empty($c['connection_string'])) {
            foreach (explode(';', (string) $c['connection_string']) as $pair) {
                $kv = explode('=', $pair, 2);
                if (count($kv) === 2) {
                    $k = strtolower(trim($kv[0]));
                    if ($k === 'instrumentationkey') { $iKey = trim($kv[1]); }
                    if ($k === 'ingestionendpoint')  { $endpoint = rtrim(trim($kv[1]), '/'); }
                }
            }
        }
        if ($iKey === '') {
            throw new Zend_Log_Exception(
                'Zend_Log_Writer_Azuremonitor requires tiger.log.azure.connection_string or .instrumentation_key.'
            );
        }
        parent::__construct();

        $this->_iKey     = $iKey;
        $this->_endpoint = $endpoint;
        $this->_role     = !empty($c['role']) ? (string) $c['role'] : 'tiger';
    }

    protected function _ship(array $events)
    {
        $envelopes = array();
        foreach ($events as $event) {
            $name  = $this->_severityName($event);
            $level = isset(self::$_severity[$name]) ? self::$_severity[$name] : 1;

            $props = array('channel' => isset($event['channel']) ? $event['channel'] : 'app');
            if (!empty($event['context']) && is_array($event['context'])) {
                // App Insights customDimensions are string->string.
                foreach ($event['context'] as $k => $v) {
                    $props[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
                }
            }

            $envelopes[] = array(
                'name' => 'Microsoft.ApplicationInsights.Message',
                'time' => isset($event['timestamp']) ? (string) $event['timestamp'] : date('c'),
                'iKey' => $this->_iKey,
                'tags' => array('ai.cloud.role' => $this->_role),
                'data' => array(
                    'baseType' => 'MessageData',
                    'baseData' => array(
                        'ver'           => 2,
                        'message'       => isset($event['message']) ? (string) $event['message'] : '',
                        'severityLevel' => $level,
                        'properties'    => $props,
                    ),
                ),
            );
        }

        $ch = curl_init($this->_endpoint . '/v2/track');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($envelopes, JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ));
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 300) {
            throw new Zend_Log_Exception('Azure ingestion failed (HTTP ' . $code . '): ' . ($err ?: $body));
        }
    }

    /**
     * @param  array|Zend_Config $config
     * @return Zend_Log_Writer_Azuremonitor
     */
    public static function factory($config)
    {
        return new self($config);
    }

    protected static function _toArray($config)
    {
        if ($config instanceof Zend_Config) { return $config->toArray(); }
        return is_array($config) ? $config : array();
    }
}
