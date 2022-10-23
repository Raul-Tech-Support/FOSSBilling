<?php

/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * main.
 */
abstract class FOSSPatchAbstract
{
    protected mixed $pdo;
    private int $version;
    private string $k = 'last_patch';

    public function __construct($di)
    {
        $this->di = $di;
        $this->pdo = $di['pdo'];
        $c = get_class($this);
        $this->version = (int) substr($c, strpos($c, '_') + 1);
    }

    abstract public function patch();

    public function donePatching(): void
    {
        $this->setParamValue($this->k, $this->version);
    }

    protected function execSql($sql): void
    {
        $stmt = $this->pdo->prepare($sql);
        try {
            $stmt->execute();
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function isPatched(): bool
    {
        return $this->getParamValue($this->k, 0) >= $this->version;
    }

    private function setParamValue($param, $value): void
    {
        if (is_null($this->getParamValue($param))) {
            $query = 'INSERT INTO setting (param, value, public, updated_at, created_at) VALUES (:param, :value, 1, :u, :c)';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['param' => $param, 'value' => $value, 'c' => date('Y-m-d H:i:s'), 'u' => date('Y-m-d H:i:s')]);
        } else {
            $query = 'UPDATE setting SET value = :value, updated_at = :u WHERE param = :param';
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['param' => $param, 'value' => $value, 'u' => date('Y-m-d H:i:s')]);
        }
    }

    private function getParamValue($param, $default = null)
    {
        $query = 'SELECT value
                FROM setting
                WHERE param = :param
               ';
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(['param' => $param]);
        $r = $stmt->fetchColumn();
        if (false === $r) {
            return $default;
        }

        return $r;
    }
}

$patches = [];
foreach (get_declared_classes() as $class) {
    if (str_contains($class, 'FOSSPatch_')) {
        $patches[] = $class;
    }
}

require_once __DIR__ . '/bb-load.php';
$di = include __DIR__ . '/bb-di.php';

error_log('Executing FOSSBilling update script');
natsort($patches);
foreach ($patches as $class) {
    $p = new $class($di);
    if (!$p->isPatched()) {
        $msg = 'FOSSBilling patch #' . $p->getVersion() . ' executing...';
        error_log($msg);
        $p->patch();
        $p->donePatching();
        $msg = 'FOSSBilling patch #' . $p->getVersion() .' was executed';
        error_log($msg);
        echo $msg . PHP_EOL;
    } else {
        error_log('Skipped patch ' . $p->getVersion());
    }
}
error_log('FOSSBilling update completed');

echo 'Update completed. You are using FOSSBilling ' . Box_Version::VERSION . PHP_EOL;
