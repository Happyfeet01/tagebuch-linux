<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000207Date20261121000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('tagebuch_entries')) {
			$table = $schema->createTable('tagebuch_entries');
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'unsigned' => true]);
			$table->setPrimaryKey(['id']);
			$table->addColumn('user_id', Types::STRING, ['length' => 64]);
			$table->addColumn('entry_date', Types::STRING, ['length' => 10]);
			$table->addColumn('entry_time', Types::STRING, ['length' => 5, 'notnull' => false]);
			foreach (['heading', 'body', 'tags', 'trackers', 'location', 'attachments'] as $column) {
				$table->addColumn($column, Types::TEXT, ['notnull' => false]);
			}
			$table->addColumn('rating', Types::INTEGER, ['notnull' => false]);
			foreach (['created_at', 'modified_at'] as $column) {
				$table->addColumn($column, Types::STRING, ['length' => 30, 'notnull' => false]);
			}
			$table->addUniqueIndex(['user_id', 'entry_date', 'entry_time'], 'tagebuch_entries_unique');
		} else {
			$table = $schema->getTable('tagebuch_entries');
		}
		if (!$table->hasColumn('encrypted')) {
			$table->addColumn('encrypted', Types::INTEGER, ['default' => 0]);
		}
		if (!$schema->hasTable('tagebuch_templates')) {
			$table = $schema->createTable('tagebuch_templates');
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'unsigned' => true]);
			$table->setPrimaryKey(['id']);
			$table->addColumn('user_id', Types::STRING, ['length' => 64]);
			$table->addColumn('name', Types::STRING, ['length' => 255]);
			$table->addColumn('body', Types::TEXT, ['notnull' => false]);
			$table->addColumn('updated_at', Types::STRING, ['length' => 30, 'notnull' => false]);
			$table->addUniqueIndex(['user_id', 'name'], 'tagebuch_templates_user_name');
		}
		return $schema;
	}
}
