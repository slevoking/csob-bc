<?php declare(strict_types = 1);

namespace AsisTeam\CSOBBC;

use AsisTeam\CSOBBC\Client\BCClientFacade;
use AsisTeam\CSOBBC\Client\BCHttpClientFactory;
use AsisTeam\CSOBBC\Client\BCSoapClientFactory;
use AsisTeam\CSOBBC\Client\Options;
use AsisTeam\CSOBBC\Enum\FileFormatEnum;
use AsisTeam\CSOBBC\Generator\FileGenerator;
use AsisTeam\CSOBBC\Generator\Payment\Impl\Mt101Generator;
use AsisTeam\CSOBBC\Generator\Payment\Impl\SepaXmlGenerator;
use AsisTeam\CSOBBC\Generator\Payment\Impl\TxtGenerator;
use AsisTeam\CSOBBC\Generator\Payment\IPaymentFileGenerator;
use AsisTeam\CSOBBC\Reader\Advice\IAdviceReader;
use AsisTeam\CSOBBC\Reader\Advice\Impl\MT942\Mt942Reader;
use AsisTeam\CSOBBC\Reader\FileReader;
use AsisTeam\CSOBBC\Reader\Import\IImportProtocolReader;
use AsisTeam\CSOBBC\Reader\Import\Impl\XmlCsob\ImprotReader;
use AsisTeam\CSOBBC\Reader\Report\Impl\XmlCsob\XmlCsobReader;
use AsisTeam\CSOBBC\Reader\Report\IReportReader;

class CEBFactory
{

	/** @var Options */
	private $options;

	/** @var string */
	private $tmpDir;

	public function __construct(Options $options, string $tmpDir)
	{
		$this->options = $options;
		$this->tmpDir = $tmpDir;
	}

	public function create(): CEB
	{
		$soapClient = (new BCSoapClientFactory())->create($this->options);
		$httpClient = (new BCHttpClientFactory())->create($this->options);

		$clientFacade = new BCClientFacade($soapClient, $httpClient);

		$reader = new FileReader($this->getReportReader(), $this->getAdviceReader(), $this->getImportProtocolReader());
		$generator = new FileGenerator();
		$generator->addGenerator(FileFormatEnum::TXT_TPS, $this->getPaymentOrderGenerator());
		$generator->addGenerator(FileFormatEnum::MT101, new Mt101Generator($this->tmpDir, true));
		$generator->addGenerator(FileFormatEnum::SEPA_XML, new SepaXmlGenerator($this->tmpDir, true));

		return new CEB($clientFacade, $reader, $generator);
	}

	protected function getReportReader(): IReportReader
	{
		return new XmlCsobReader();
	}

	protected function getAdviceReader(): IAdviceReader
	{
		return new Mt942Reader();
	}

	protected function getImportProtocolReader(): IImportProtocolReader
	{
		return new ImprotReader();
	}

	protected function getPaymentOrderGenerator(): IPaymentFileGenerator
	{
		return new TxtGenerator($this->tmpDir, true);
	}

}
