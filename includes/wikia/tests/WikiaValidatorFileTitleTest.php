<?php

class WikiaValidatorFileTitleTest extends WikiaBaseTest {

	/**
	 * @group Slow
	 * @group Broken
	 * @slowExecutionTime 0.03433 ms
	 * @dataProvider filesDataProvider
	 */
	public function testFiles($fileString, $isValid, $exists) {

		$fileMock = $this->getMock('WikiaLocalFile', array('exists'), array(), '', false);
		$fileMock->expects($this->once())
			->method('exists')
			->will($this->returnValue($exists));

		$mockFindFile = $this->getGlobalFunctionMock( 'wfFindFile' );
		$mockFindFile
			->expects($this->once())
			->method('wfFindFile')
			->will($this->returnValue($fileMock));

		$validator = $this->getMock('WikiaValidatorFileTitle', array('getApp'));

		$titleMock = $this->getMock('Title', array('newFromText'));
		$titleMock->expects($this->any())
			->method('exists')
			->will($this->returnValue($exists));

		$validator->setTitleClass($titleMock);

		$result = $validator->isValid($fileString);
		$this->assertEquals($isValid, $result);
	}

	public function filesDataProvider() {
		return array(
			array('Skyfall (2012) - Theatrical Trailer 2 for Skyfall', true, true),
			array('Skyfall (2012) - Theatrical Trailer 2 for Skyfall', false, false),
			array('Skyfall (2012) - Theatrical Trailer 2 for Skyfall', false, false),
			array('James Bond', true, true),
			array('Skyfall (2012) - Theatrical Trailer 2 for Skyfall', false, false),
		);
	}
}
