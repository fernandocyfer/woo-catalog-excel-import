<?php
/**
 * Resultado agregado da importação.
 *
 * @package WCSPI
 */

namespace WCSPI\DTO;

defined( 'ABSPATH' ) || exit;

final class Import_Result {

	private int $created = 0;
	private int $updated = 0;

	/**
	 * @var list<array{line:int, message:string}>
	 */
	private array $errors = array();

	/**
	 * @var list<array{line:int, type:string, sku:string}>
	 */
	private array $log_lines = array();

	public function increment_created(): void {
		++$this->created;
	}

	public function increment_updated(): void {
		++$this->updated;
	}

	/**
	 * @param array{line:int, message:string} $error
	 */
	public function add_error( array $error ): void {
		$this->errors[] = $error;
	}

	/**
	 * @param array{line:int, type:string, sku:string} $entry
	 */
	public function add_log( array $entry ): void {
		$this->log_lines[] = $entry;
	}

	public function created(): int {
		return $this->created;
	}

	public function updated(): int {
		return $this->updated;
	}

	/**
	 * @return list<array{line:int, message:string}>
	 */
	public function errors(): array {
		return $this->errors;
	}

	public function error_count(): int {
		return count( $this->errors );
	}

	/**
	 * @return list<array{line:int, type:string, sku:string}>
	 */
	public function log_lines(): array {
		return $this->log_lines;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'created'      => $this->created,
			'updated'      => $this->updated,
			'error_count'  => $this->error_count(),
			'errors'       => $this->errors,
			'log'          => $this->log_lines,
			'finished_at'  => gmdate( 'c' ),
		);
	}
}
