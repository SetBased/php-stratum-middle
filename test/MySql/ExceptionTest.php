<?php

namespace SetBased\Stratum\Test\MySql;

use SetBased\Exception\RuntimeException;

/**
 * Test with illegal queries.
 */
class ExceptionTest extends DataLayerTestCase
{
  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @expectedException RuntimeException
   */
  public function test1()
  {
    $this->dataLayer->tstTestIllegalQuery();
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
