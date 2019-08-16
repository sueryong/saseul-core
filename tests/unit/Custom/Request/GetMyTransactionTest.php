<?php

use PHPUnit\Framework\TestCase;
use Saseul\Common\AbstractRequest;
use Saseul\Custom\Request\GetMyTransaction;

class GetMyTransactionTest extends TestCase
{
    public function testSutInheritsAbstractRequest()
    {
        # Arrange
        $sut = new GetMyTransaction();

        # Assert
        $this->assertInstanceOf(AbstractRequest::class, $sut);
    }
}