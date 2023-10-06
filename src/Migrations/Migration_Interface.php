<?php

namespace TEC\Events\Innovation_Day\Union_Queries\Migrations;

interface Migration_Interface {
	public function up(): void;

	public function down(): void;
}
