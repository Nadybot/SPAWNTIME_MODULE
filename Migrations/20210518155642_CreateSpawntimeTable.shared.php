<?php declare(strict_types=1);

namespace Nadybot\User\Modules\SPAWNTIME_MODULE\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Nadybot\Core\DB;
use Nadybot\Core\LoggerWrapper;
use Nadybot\Core\SchemaMigration;

class CreateSpawntimeTable implements SchemaMigration {
	public function migrate(LoggerWrapper $logger, DB $db): void {
		$table = "spawntime";
		$db->schema()->dropIfExists($table);
		$db->schema()->create($table, function(Blueprint $table) {
			$table->string("mob", 50)->primary();
			$table->string("placeholder", 50)->nullable();
			$table->boolean("can_skip_spawn")->nullable();
			$table->integer("spawntime")->nullable();
		});
	}
}
