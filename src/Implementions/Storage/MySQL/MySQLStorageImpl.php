<?php
namespace InteractivePlus\PDK2021\Implementions\Storage\MySQL;
interface MySQLStorageImpl{
    public function createTables() : void;
    public function clearTables() : void;
    public function deleteTables() : void;
}