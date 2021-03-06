<?php

namespace Saseul\Test\Unit\Custom\Request;

use PHPUnit\Framework\TestCase;
use Saseul\Custom\Request\AbstractRequest;
use Saseul\Custom\Request\GetAccountInfo;

class GetAccountInfoTest extends TestCase
{
    public function testSutInheritsAbstractRequest(): void
    {
        // Arrange
        $sut = new GetAccountInfo();

        // Assert
        $this->assertInstanceOf(AbstractRequest::class, $sut);
    }
}
