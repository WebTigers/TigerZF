<?php
/**
 * Zend_Log_Writer_Cloudwatch — AWS CloudWatch Logs writer.
 *
 * TigerZF addition. Ships batched JSON events to a CloudWatch Logs group/stream via
 * PutLogEvents. The AWS SDK (aws/aws-sdk-php) is an OPTIONAL dependency — this class
 * loads fine without it; the constructor throws only when you actually select this
 * writer without the SDK present, so the caller (Tiger_Log) can fall back cleanly.
 *
 * NOTE: on AWS the usual best practice is still `errorlog`/`stderr` + the CloudWatch
 * agent — zero app dependencies. Reach for this direct writer when you want the app
 * to own log-group routing (e.g. per-tenant streams) without an agent.
 *
 * Config (tiger.log.cloudwatch.* — see core.ini):
 *   group   (required)  log group name, e.g. "/tiger/prod/app"
 *   stream  (optional)  log stream name (default: gethostname())
 *   region  (optional)  AWS region (default: env AWS_REGION or us-east-1)
 *
 * Credentials come from the default AWS chain (instance role / env / profile) — we
 * never take keys in config.
 *
 * @category   Zend
 * @package    Zend_Log
 * @subpackage Writer
 */
class Zend_Log_Writer_Cloudwatch extends Zend_Log_Writer_Buffered
{
    /** @var string */ protected $_group;
    /** @var string */ protected $_stream;
    /** @var string */ protected $_region;
    /** @var object|null memoized SDK client */ protected $_client = null;
    /** @var bool have we ensured the group+stream exist this process */ protected $_ensured = false;

    /**
     * @param  array|Zend_Config|null $config
     * @throws Zend_Log_Exception when the AWS SDK is absent or no group is configured
     */
    public function __construct($config = null)
    {
        if (!class_exists('Aws\\CloudWatchLogs\\CloudWatchLogsClient')) {
            throw new Zend_Log_Exception(
                'Zend_Log_Writer_Cloudwatch requires aws/aws-sdk-php (composer require aws/aws-sdk-php).'
            );
        }
        $c = self::_toArray($config);
        if (empty($c['group'])) {
            throw new Zend_Log_Exception('Zend_Log_Writer_Cloudwatch requires tiger.log.cloudwatch.group.');
        }
        parent::__construct();

        $this->_group  = (string) $c['group'];
        $this->_stream = !empty($c['stream']) ? (string) $c['stream'] : (gethostname() ?: 'tiger');
        $this->_region = !empty($c['region']) ? (string) $c['region']
                       : (getenv('AWS_REGION') ?: 'us-east-1');
    }

    protected function _client()
    {
        if ($this->_client === null) {
            $class = 'Aws\\CloudWatchLogs\\CloudWatchLogsClient';
            $this->_client = new $class(array('version' => '2014-03-28', 'region' => $this->_region));
        }
        return $this->_client;
    }

    /** Create the group + stream if missing (idempotent; ignore "already exists"). */
    protected function _ensure()
    {
        if ($this->_ensured) {
            return;
        }
        $this->_ensured = true;
        foreach (array(
            array('createLogGroup',  array('logGroupName' => $this->_group)),
            array('createLogStream', array('logGroupName' => $this->_group, 'logStreamName' => $this->_stream)),
        ) as $call) {
            try { $this->_client()->{$call[0]}($call[1]); }
            catch (Throwable $e) { /* ResourceAlreadyExistsException — fine */ }
        }
    }

    protected function _ship(array $events)
    {
        $this->_ensure();

        $logEvents = array();
        foreach ($events as $event) {
            $logEvents[] = array(
                'timestamp' => $this->_epochMillis($event),
                'message'   => rtrim($this->_formatter->format($event), "\r\n"),
            );
        }
        // CloudWatch requires events in chronological order within a batch.
        usort($logEvents, function ($a, $b) { return $a['timestamp'] <=> $b['timestamp']; });

        $this->_client()->putLogEvents(array(
            'logGroupName'  => $this->_group,
            'logStreamName' => $this->_stream,
            'logEvents'     => $logEvents,
        ));
    }

    /**
     * @param  array|Zend_Config $config
     * @return Zend_Log_Writer_Cloudwatch
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
