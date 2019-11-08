<?php
declare(strict_types=1);

namespace SetBased\Stratum\Middle\Exception;

use SetBased\Exception\RuntimeException;

/**
 * Exception for situations where the result (set) of a query does not meet the expectations. Either a mismatch between
 * the actual and expected numbers of rows selected or an unexpected NULL value was selected.
 */
class ResultException extends RuntimeException
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * The actual number of selected rows selected.
   *
   * @var int
   */
  private $actualRowCount;

  /**
   * The expected number selected of rows selected.
   *
   * @var int[]
   */
  private $expectedRowCount;

  /**
   * The executed SQL query.
   *
   * @var string
   */
  private $query;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Object constructor.
   *
   * @param int[]  $expectedRowCount The expected number of rows selected.
   * @param int    $actualRowCount   The actual number of rows selected.
   * @param string $message          The SQL query
   *
   * @since 4.0.0
   * @api
   */
  public function __construct(array $expectedRowCount, int $actualRowCount, string $message)
  {
    parent::__construct(self::message($expectedRowCount, $actualRowCount, $message));

    $this->expectedRowCount = $expectedRowCount;
    $this->actualRowCount   = $actualRowCount;
    $this->query            = $message;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the actual number of selected rows.
   *
   * @return int
   *
   * @since 4.0.0
   * @api
   */
  public function getActualNumberRows(): int
  {
    return $this->actualRowCount;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the expected number of selected rows.
   *
   * @return int[]
   *
   * @since 4.0.0
   * @api
   */
  public function getExpectedNumberRows(): array
  {
    return $this->expectedRowCount;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritdoc
   *
   * @since 4.0.0
   * @api
   */
  public function getName()
  {
    return 'Wrong row count';
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Returns the executed SQL query.
   *
   * @return string
   *
   * @since 4.0.0
   * @api
   */
  public function getQuery(): string
  {
    return $this->query;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Composes the exception message.
   *
   * @param int[]  $expectedRowCount The expected number of rows selected.
   * @param int    $actualRowCount   The actual number of rows selected.
   * @param string $query            The SQL query.
   *
   * @return string
   *
   * @since 4.0.0
   * @api
   */
  private function message(array $expectedRowCount, int $actualRowCount, string $query): string
  {
    $query = trim($query);

    $message = 'Wrong number of rows selected.';
    $message .= "\n";
    $message .= sprintf("Expected number of rows: %s.\n", implode(', ', $expectedRowCount));
    $message .= sprintf("Actual number of rows: %s.\n", $actualRowCount);
    $message .= 'Query:';
    $message .= (strpos($query, "\n")!==false) ? "\n" : ' ';
    $message .= $query;

    return $message;
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
