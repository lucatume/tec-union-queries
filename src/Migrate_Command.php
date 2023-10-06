<?php

namespace TEC\Events\Innovation_Day\Union_Queries;

class Migrate_Command {
	public function migrate(): void {
		foreach ( $this->get_migrations() as $migration ) {
			$migration->up();
		}
	}

	public function rollback(): void {
		foreach ( $this->get_migrations() as $migration ) {
			$migration->down();
		}
	}

	public function get_migrations(): \Generator {
		$registered_migrations = apply_filters( 'tec_migrations', [] );

		foreach ( $registered_migrations as $migration ) {
			if ( ! class_exists( $migration ) ) {
				continue;
			}

			if ( ! class_implements( $migration, Migrations\Migration_Interface::class ) ) {
				throw new \InvalidArgumentException(
					sprintf( 'Migration %s does not implement the Migration_Interface', $migration )
				);
			}

			yield tribe( $migration );
		}
	}
}
