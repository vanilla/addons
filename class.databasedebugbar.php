<?php

use DebugBar\DataCollector\PDO\TraceablePDO;
use DebugBar\DataCollector\PDO\PDOCollector;

class DatabaseDebugbar extends Gdn_Database {
    /// Methods ///

    /**
     *
     * @param \DebugBar\DebugBar $debugbar
     */
    public function addCollector($debugbar) {
        $connection = $this->Connection();
        if (!($connection instanceof TraceablePDO)) {
            $connection = new TraceablePDO($connection);
            $this->_Connection = $connection;

            $collector = new PDOCollector();
            $collector->addConnection($connection, 'master');
            $debugbar->addCollector($collector);
        }

        $slave = $this->Slave();
        if (isset($collector) && !($slave instanceof TraceablePDO)) {
            $slave = new TraceablePDO($slave);
            $this->_Slave = $slave;
            $collector->addConnection($slave, 'slave');
        }
    }
}
