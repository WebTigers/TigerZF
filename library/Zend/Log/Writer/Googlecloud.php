<?php
/**
 * Zend_Log_Writer_Googlecloud — Google Cloud Logging (Stackdriver) writer.
 *
 * TigerZF addition. Ships batched structured entries to Cloud Logging via
 * writeBatch(). The SDK (google/cloud-logging) is an OPTIONAL dependency — this
 * class loads fine without it; the constructor throws only when you select this
 * writer without the SDK present, so Tiger_Log can fall back cleanly.
 *
 * NOTE: on GCP the usual best practice is still `errorlog`/`stderr` + the Ops Agent
 * (it parses JSON stdout into structured entries for free). Reach for this direct
 * writer when you want app-controlled log names / resources.
 *
 * Config (tiger.log.gcp.* — see core.ini):
 *   project  (optional)  GCP project id (default: SDK auto-detects on GCE/GKE)
 *   log      (optional)  log name (default: "tiger")
 *
 * Credentials come from Application Default Credentials (GOOGLE_APPLICATION_
 * CREDENTIALS or the metadata server) — never from config.
 *
 * @category   Zend
 * @package    Zend_Log
 * @subpackage Writer
 */
class Zend_Log_Writer_Googlecloud extends Zend_Log_Writer_Buffered
{
    /** @var string|null */ protected $_project;
    /** @var string */      protected $_logName;
    /** @var object|null */ protected $_logger = null;

    /** Zend_Log priority name -> Cloud Logging severity. */
    protected static $_severity = array(
        'DEBUG' => 'DEBUG', 'INFO' => 'INFO', 'NOTICE' => 'NOTICE',
        'WARN'  => 'WARNING', 'ERR' => 'ERROR', 'CRIT' => 'CRITICAL',
        'ALERT' => 'ALERT', 'EMERG' => 'EMERGENCY',
    );

    /**
     * @param  array|Zend_Config|null $config
     * @throws Zend_Log_Exception when the Google Cloud Logging SDK is absent
     */
    public function __construct($config = null)
    {
        if (!class_exists('Google\\Cloud\\Logging\\LoggingClient')) {
            throw new Zend_Log_Exception(
                'Zend_Log_Writer_Googlecloud requires google/cloud-logging (composer require google/cloud-logging).'
            );
        }
        parent::__construct();

        $c = self::_toArray($config);
        $this->_project = !empty($c['project']) ? (string) $c['project'] : null;
        $this->_logName = !empty($c['log']) ? (string) $c['log'] : 'tiger';
    }

    protected function _logger()
    {
        if ($this->_logger === null) {
            $class   = 'Google\\Cloud\\Logging\\LoggingClient';
            $options = $this->_project ? array('projectId' => $this->_project) : array();
            $client  = new $class($options);
            $this->_logger = $client->logger($this->_logName);
        }
        return $this->_logger;
    }

    protected function _ship(array $events)
    {
        $logger  = $this->_logger();
        $entries = array();
        foreach ($events as $event) {
            $name     = $this->_severityName($event);
            $severity = isset(self::$_severity[$name]) ? self::$_severity[$name] : 'DEFAULT';

            // Structured payload: Logs Explorer indexes jsonPayload.* fields.
            $payload = array(
                'message' => isset($event['message']) ? (string) $event['message'] : '',
                'channel' => isset($event['channel']) ? $event['channel'] : 'app',
            );
            if (!empty($event['context']) && is_array($event['context'])) {
                $payload['context'] = $event['context'];
            }

            $entries[] = $logger->entry($payload, array('severity' => $severity));
        }
        $logger->writeBatch($entries);
    }

    /**
     * @param  array|Zend_Config $config
     * @return Zend_Log_Writer_Googlecloud
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
