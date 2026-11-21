<?php
declare(strict_types=1);

namespace OCA\Tagebuch\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000300Date20261121000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$schema->getTable('tagebuch_entries')->modifyColumn('entry_time', ['length' => 18]);
		if (!$schema->hasTable('tagebuch_state')) {
			$table = $schema->createTable('tagebuch_state');
			$table->addColumn('user_id', Types::STRING, ['length' => 64]);
			$table->addColumn('state_key', Types::STRING, ['length' => 80]);
			$table->addColumn('revision', Types::STRING, ['length' => 32]);
			$table->addColumn('payload', Types::TEXT);
			$table->setPrimaryKey(['user_id', 'state_key']);
		}
		return $schema;
	}
}
