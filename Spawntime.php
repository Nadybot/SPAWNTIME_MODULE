<?php declare(strict_types=1);

namespace Nadybot\User\Modules\SPAWNTIME_MODULE;

use Illuminate\Support\Collection;
use Nadybot\Core\DBRow;
use Nadybot\Modules\WHEREIS_MODULE\WhereisResult;

class Spawntime extends DBRow {
	public string $mob;
	public ?string $placeholder = null;
	public ?bool $can_skip_spawn = null;
	public ?int $spawntime = null;

	/** @var Collection<WhereisResult> */
	public Collection $coordinates;
}
