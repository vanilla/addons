<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Vanilla\DebugBar;

use DebugBar\DataCollector\PDO\TraceablePDO;
use DebugBar\DataCollector\PDO\PDOCollector;

class DatabaseDebugBar extends \Gdn_Database {
    /// Methods ///

    /**
     *
     * @param \DebugBar\DebugBar $debugBar
     */
    public function addCollector($debugBar) {
        $connection = $this->Connection();
        if (!($connection instanceof TraceablePDO)) {
            $connection = new TraceablePDO($connection);
            $this->_Connection = $connection;

            $collector = new PDOCollector();
            $collector->addConnection($connection, 'master');
            $debugBar->addCollector($collector);
        }

        $slave = $this->Slave();
        if (isset($collector) && !($slave instanceof TraceablePDO)) {
            $slave = new TraceablePDO($slave);
            $this->_Slave = $slave;
            $collector->addConnection($slave, 'slave');
        }
    }
}
