<?php declare(strict_types=1);

namespace Nadybot\User\Modules\SPAWNTIME_MODULE;

use Illuminate\Support\Collection;
use Nadybot\Core\CommandReply;
use Nadybot\Core\DB;
use Nadybot\Core\LoggerWrapper;
use Nadybot\Core\Text;
use Nadybot\Core\Util;

/**
 * @author Nadyita (RK5) <nadyita@hodorraid.org>
 *
 * @Instance
 *
 * Commands this controller contains:
 *	@DefineCommand(
 *		command     = 'spawntime',
 *		accessLevel = 'all',
 *		description = 'Show (re)spawntimers',
 *		alias       = 'spawn',
 *		help        = 'spawntime.txt'
 *	)
 */

class SpawntimeController {
	public string $moduleName;

	/** @Inject */
	public DB $db;

	/** @Inject */
	public Text $text;

	/** @Inject */
	public Util $util;

	/** @Logger */
	public LoggerWrapper $logger;

	/**
	 * @Setup
	 * This handler is called on bot startup.
	 */
	public function setup(): void {
		// load database tables from .sql-files
		$this->db->loadMigrations($this->moduleName, __DIR__ . '/Migrations');
		$this->db->loadCSVFile($this->moduleName, __DIR__ . '/spawntime.csv');
	}

	/**
	 * @return string[]
	 */
	public function getLocationBlob(Spawntime $spawntime): string {
		$blob = '';
		foreach ($spawntime->coordinates as $row) {
			$blob .= "<header2>$row->name<end>\n$row->answer";
			if ($row->playfield_id !== 0 && $row->xcoord !== 0 && $row->ycoord !== 0) {
				$blob .= " " . $this->text->makeChatcmd("waypoint: {$row->xcoord}x{$row->ycoord} {$row->short_name}", "/waypoint {$row->xcoord} {$row->ycoord} {$row->playfield_id}");
			}
			$blob .= "\n\n";
		}
		return $this->text->makeBlob("locations (" . count($spawntime->coordinates).")", $blob);
	}

	/**
	 * Return the formatted entry for one mob
	 */
	protected function getMobLine(Spawntime $row, bool $displayDirectly): string {
		$line = "<highlight>{$row->mob}<end>: ";
		if ($row->spawntime !== null) {
			$line .= "<orange>" . strftime('%Hh%Mm%Ss', $row->spawntime) . "<end>";
		} else {
			$line .= "<orange>&lt;unknown&gt;<end>";
		}
		$line = preg_replace('/00[hms]/', '', $line);
		$line = preg_replace('/>0/', '>', $line);
		$flags = [];
		if ($row->can_skip_spawn) {
			$flags[] = 'can skip spawn';
		}
		if (strlen($row->placeholder??"")) {
			$flags[] = "placeholder: " . $row->placeholder;
		}
		if (count($flags)) {
			$line .= ' (' . join(', ', $flags) . ')';
		}
		if ($displayDirectly === true && $row->coordinates->count()) {
			$line .= " [" . $this->getLocationBlob($row) . "]";
		} elseif ($row->coordinates->count() > 1) {
			$line .= " [" .
				$this->text->makeChatcmd(
					"locations (" . count($row->coordinates) . ")",
					"/tell <myname> whereis " . $row->mob
				).
				"]";
		} elseif ($row->coordinates->count() === 1) {
			$coords = $row->coordinates->first();
			if ($coords->playfield_id != 0 && $coords->xcoord != 0 && $coords->ycoord != 0) {
				$line .= " [".
					$this->text->makeChatcmd(
						"{$coords->xcoord}x{$coords->ycoord} {$coords->short_name}",
						"/waypoint {$coords->xcoord} {$coords->ycoord} {$coords->playfield_id}"
					).
					"]";
			}
		}
		return $line;
	}

	/**
	 * Command to list all Spawntimes
	 *
	 * @HandlesCommand("spawntime")
	 * @Matches("/^spawntime$/i")
	 */
	public function spawntimeListCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$spawnTimes = $this->db->table("spawntime")->asObj(Spawntime::class);
		if ($spawnTimes->isEmpty()) {
			$msg = 'There are currently no spawntimes in the database.';
			$sendto->reply($msg);
			return;
		}
		$timeLines = $this->spawntimesToLines($spawnTimes);
		$msg = $this->text->makeBlob('All known spawntimes', $timeLines->join("\n"));
		$sendto->reply($msg);
	}

	/**
	 * Command to list all Spawntimes
	 *
	 * @HandlesCommand("spawntime")
	 * @Matches("/^spawntime (.+)$/i")
	 */
	public function spawntimeSearchCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$args[1] = trim($args[1]);
		$tokens = explode(" ", $args[1]);
		$query = $this->db->table("spawntime");
		$this->db->addWhereFromParams($query, $tokens, "mob");
		$this->db->addWhereFromParams($query, $tokens, "placeholder", "or");
		$spawnTimes = $query->asObj(Spawntime::class);
		if ($spawnTimes->isEmpty()) {
			$msg = "No spawntime matching <highlight>{$args[1]}<end>.";
			$sendto->reply($msg);
			return;
		}
		$timeLines = $this->spawntimesToLines($spawnTimes);
		$count = $timeLines->count();
		if ($count === 1) {
			$msg = $timeLines->first();
		} elseif ($count < 4) {
			$msg = "Spawntimes matching <highlight>{$args[1]}<end>:\n".
				$timeLines->join("\n");
		} else {
			$msg = $this->text->makeBlob(
				"Spawntimes for \"{$args[1]}\" ($count)",
				$timeLines->join("\n")
			);
		}
		$sendto->reply($msg);
	}

	/**
	 * @param Collection<Spawntime> $spawnTimes
	 * @return Collection<string>
	 */
	protected function spawntimesToLines(Collection $spawnTimes): Collection {
		$locations = $this->db->table("whereis as w")
			->join("playfields as p", "p.id", "w.playfield_id")
			->asObj(WhereisCoordinates::class);
		$spawnTimes->each(function (Spawntime $spawn) use ($locations) {
			$spawn->coordinates = $locations->filter(function (WhereisCoordinates $coords) use ($spawn): bool {
				return strncasecmp($coords->name, $spawn->mob, strlen($spawn->mob)) === 0;
			});
		});
		$displayDirectly = $spawnTimes->count() < 4;
		return $spawnTimes->map(function(Spawntime $spawn) use ($displayDirectly) {
			return $this->getMobLine($spawn, $displayDirectly);
		});
	}
}
