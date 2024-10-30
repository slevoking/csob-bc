<?php declare(strict_types = 1);

namespace AsisTeam\CSOBBC\Generator\Payment;

use AsisTeam\CSOBBC\Entity\IFile;
use AsisTeam\CSOBBC\Entity\IPaymentOrder;

interface IPaymentFileGenerator
{

	public const TYPE_INLAND = 'inland';
	public const TYPE_FOREIGN = 'foreign';
	public const TYPE_SEPA = 'sepa';

	/**
	 * @param IPaymentOrder[] $payments
	 */
	public function generate(array $payments, ?string $filename, string $type): IFile;

}
