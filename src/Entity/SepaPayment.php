<?php declare(strict_types = 1);

namespace AsisTeam\CSOBBC\Entity;

use AsisTeam\CSOBBC\Enum\ForeignPaymentCharge;
use AsisTeam\CSOBBC\Exception\Logical\InvalidArgumentException;
use AsisTeam\CSOBBC\Exception\LogicalException;
use DateTimeImmutable;
use Money\Money;

final class SepaPayment implements IPaymentOrder
{

	/** @var string */
	private $originatorAccountNumber; // may be in ABO or IBAN format

	/** @var string */
	private $originatorReference;

	/** @var string */
	private $paymentDay;

	/** @var Money */
	private $amount;

	/** @var string */
	private $counterpartyIBAN;

	/** @var string */
	private $purpose;

	private $counterpartyName;

	public function __construct(
		string $originatorAccountNumber,
		Money $amount,
		string $paymentDay = '99',
		string $counterpartyName,
		string $counterpartyIBAN,
		string $purpose,
		string $originatorReference = ''
	)
	{
		$this->setOriginatorAccountNumber($originatorAccountNumber);
		$this->setAmount($amount);
		$this->setCounterpartyIban($counterpartyIBAN);
		$this->setPurpose($purpose);
		$this->setOriginatorReference($originatorReference);

		$this->paymentDay = $paymentDay;
		$this->counterpartyName = $counterpartyName;
	}

	public function getOriginatorAccountNumber(): string
	{
		return $this->originatorAccountNumber;
	}

	public function getCounterpartyName(): string
	{
		return $this->counterpartyName;
	}

	public function setOriginatorAccountNumber(string $originatorAccountNumber): self
	{
		//if (strlen($originatorAccountNumber) > 24) {
		//	throw new InvalidArgumentException('Originator bank account must not contain more then 24 digits');
		//}

		$this->originatorAccountNumber = trim($originatorAccountNumber);

		return $this;
	}

	public function getOriginatorReference(): string
	{
		return $this->originatorReference;
	}

	public function setOriginatorReference(string $originatorReference): self
	{
		$this->originatorReference = substr($originatorReference, 0, 16);

		return $this;
	}

	public function getAmount(): Money
	{
		return $this->amount;
	}

	public function setAmount(Money $amount): self
	{
		if (!$amount->isPositive()) {
			throw new LogicalException('Payment amount must be positive number');
		}

		$this->amount = $amount;

		return $this;
	}

	public function getCounterpartyIban(): string
	{
		return $this->counterpartyIBAN;
	}

	public function setCounterpartyIban(string $counterpartyIBAN): self
	{
		$this->counterpartyIBAN = trim($counterpartyIBAN);

		return $this;
	}

	public function getPurpose(): string
	{
		return $this->purpose;
	}

	public function setPurpose(string $purpose): self
	{
		if (strlen($purpose) < 3) {
			throw new InvalidArgumentException('Purpose of the payment must contain at least 3 characters');
		}

		$this->purpose = $purpose;

		return $this;
	}

	public function getPaymentDay(): string
	{
		return $this->paymentDay;
	}

}
