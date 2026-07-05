<?php
/**
 * Zend_Log_Writer_Buffered — abstract base for batch/remote log writers.
 *
 * TigerZF addition. Remote sinks (CloudWatch, Cloud Logging, Azure Monitor) must
 * NOT make an API round-trip per log line — that would put network latency on
 * every request. This base buffers events in memory and ships them in batches:
 *   - when the buffer hits _max, and
 *   - on shutdown (register_shutdown_function + the writer's shutdown() hook,
 *     which Zend_Log calls from its destructor).
 *
 * Subclasses implement _ship(array $events). Shipping is wrapped so a transport
 * failure NEVER propagates into the request — a failed batch degrades to
 * error_log() (which the platform agent still collects) rather than throwing on
 * the caller's error path.
 *
 * @category   Zend
 * @package    Zend_Log
 * @subpackage Writer
 */
abstract class Zend_Log_Writer_Buffered extends Zend_Log_Writer_Abstract
{
    /** @var array buffered events awaiting ship */
    protected $_events = array();

    /** @var int flush the buffer once it reaches this many events */
    protected $_max = 200;

    /** @var bool guard against a double shutdown flush */
    protected $_shutdown = false;

    public function __construct()
    {
        $this->_formatter = new Zend_Log_Formatter_Json();
        register_shutdown_function(array($this, 'shutdown'));
    }

    /** Buffer the event; flush eagerly when the batch is full. */
    protected function _write($event)
    {
        $this->_events[] = $event;
        if (count($this->_events) >= $this->_max) {
            $this->flush();
        }
    }

    /** Zend_Log calls this from its destructor; the shutdown function does too. */
    public function shutdown()
    {
        if ($this->_shutdown) {
            return;
        }
        $this->_shutdown = true;
        $this->flush();
    }

    /** Ship whatever is buffered, then clear it. Never throws. */
    public function flush()
    {
        if (empty($this->_events)) {
            return;
        }
        $events = $this->_events;
        $this->_events = array();

        try {
            $this->_ship($events);
        } catch (Throwable $e) {
            // Degrade, don't die: the platform agent still collects error_log().
            foreach ($events as $event) {
                error_log('[tiger_log_fallback] ' . rtrim($this->_formatter->format($event), "\r\n"));
            }
            error_log('[tiger_log_ship_failed] ' . get_class($this) . ': ' . $e->getMessage());
        }
    }

    /**
     * Deliver a batch of events to the remote sink. Implementations may throw;
     * flush() catches and falls back.
     *
     * @param array $events
     */
    abstract protected function _ship(array $events);

    /** Map a Zend_Log priority to an uppercase severity name (shared helper). */
    protected function _severityName($event)
    {
        return isset($event['priorityName']) ? (string) $event['priorityName'] : 'INFO';
    }

    /** Epoch milliseconds for an event (CloudWatch/others want ms). */
    protected function _epochMillis($event)
    {
        $ts = isset($event['timestamp']) ? strtotime((string) $event['timestamp']) : false;
        if ($ts === false) {
            $ts = time();
        }
        return $ts * 1000;
    }
}
