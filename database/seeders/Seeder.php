<?php
namespace Database\Seeders;
use Faravel\Database\Database;

abstract class Seeder {
    protected Database $db;
    public function __construct(Database $db) { $this->db = $db; }
    abstract public function run(): void;
}
