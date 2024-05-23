<?php

namespace EDI\XRechnung;

use DOMDocument;
use DOMElement;
use DOMException;
use Exception;

class Invoice {

	#region CONSTANTS

	const CUSTOMIZATION_ID = 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0';
	const PROFILE_ID       = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

	const INVOICE_TYPE_INVOICE            = 380;
	const INVOICE_TYPE_CREDIT_NOTE        = 381;
	const INVOICE_TYPE_INVOICE_CORRECTION = 384;

	const PARTY_SELLER = 'AccountingSupplierParty';
	const PARTY_BUYER  = 'AccountingCustomerParty';

	// PAYMENT MEANS CODES
	const PAYMENT_METHOD_SEPA_CREDIT_TRANSFER     = 58;
	const PAYMENT_METHOD_SEPA_DIRECT_DEBIT        = 59;
	const PAYMENT_METHOD_CREDIT_TRANSFER_NON_SEPA = 30;
	const PAYMENT_METHOD_CHEQUE                   = 20;
	const PAYMENT_METHOD_IN_CASH                  = 10;
	const PAYMENT_METHOD_CREDIT_CARD              = 54;

	#endregion

	#region FIELDS

	private DOMDocument $dom;
	private DOMElement  $invoice;
	private string      $invoiceTypeCode;

	#endregion

	/**
	 * @param int $invoiceTypeCode The type of the invoice. Use one of the constants defined in this class
	 *
	 * @throws DOMException
	 * @throws Exception
	 */
	public function __construct (int $invoiceTypeCode) {

		if (!in_array ($invoiceTypeCode, [self::INVOICE_TYPE_INVOICE, self::INVOICE_TYPE_CREDIT_NOTE, self::INVOICE_TYPE_INVOICE_CORRECTION])) {
			throw new Exception("Invalid invoiceTypeCode");
		}

		$this->invoiceTypeCode = $invoiceTypeCode;

		$this->dom               = new DOMDocument('1.0', 'UTF-8');
		$this->dom->formatOutput = true;
		$this->invoice           = $this->dom->createElementNS ('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
		$this->invoice->setAttribute ('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
		$this->invoice->setAttribute ('xmlns:cec', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
		$this->invoice->setAttribute ('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
		$this->dom->appendChild ($this->invoice);
	}

	#region PUBLIC METHODS

	/**
	 * @param string $invoiceId
	 * @param string $issueDate
	 * @param string $dueDate
	 * @param string $documentCurrencyCode
	 * @param string $buyerReference
	 *
	 * @return void
	 * @throws DOMException
	 */
	public function SetBasicInformation (string $invoiceId, string $issueDate, string $dueDate, string $documentCurrencyCode, string $buyerReference): void {
		$this->AddElement ($this->invoice, 'cbc:CustomizationID', self::CUSTOMIZATION_ID);
		$this->AddElement ($this->invoice, 'cbc:ProfileID', self::PROFILE_ID);
		$this->AddElement ($this->invoice, 'cbc:ID', $invoiceId);
		$this->AddElement ($this->invoice, 'cbc:IssueDate', $issueDate);
		$this->AddElement ($this->invoice, 'cbc:DueDate', $dueDate);
		$this->AddElement ($this->invoice, 'cbc:InvoiceTypeCode', $this->invoiceTypeCode);
		$this->AddElement ($this->invoice, 'cbc:DocumentCurrencyCode', $documentCurrencyCode);
		$this->AddElement ($this->invoice, 'cbc:BuyerReference', $buyerReference);
	}

	/**
	 * @param string $endpointId The endpoint ID of the seller, this can be an email address or a URL
	 * @param string $schemeId The appropriate scheme ID for the endpoint ID
	 * @param string $companyName
	 * @param string $street
	 * @param string $city
	 * @param string $postalCode
	 * @param string $countryCode
	 * @param string $taxNumber
	 * @param string $vatIdentificationNumber
	 * @param string $contactName
	 * @param string $telephone
	 * @param string $email
	 *
	 * @return void
	 * @throws DOMException
	 */
	public function AddSellerParty (string $endpointId, string $schemeId, string $companyName, string $street, string $city, string $postalCode, string $countryCode, string $taxNumber, string $vatIdentificationNumber, string $contactName, string $telephone, string $email): void {
		$this->AddParty (self::PARTY_SELLER, $endpointId, $schemeId, $companyName, $street, $city, $postalCode, $countryCode, $taxNumber, $vatIdentificationNumber, $contactName, $telephone, $email);
	}

	/**
	 * @param string $endpointId The endpoint ID of the seller, this can be an email address or a URL
	 * @param string $schemeId   The appropriate scheme ID for the endpoint ID
	 * @param string $companyName
	 * @param string $street
	 * @param string $city
	 * @param string $postalCode
	 * @param string $countryCode
	 * @param string $taxNumber
	 * @param string $vatIdentificationNumber
	 * @param string $contactName
	 * @param string $telephone
	 * @param string $email
	 *
	 * @return void
	 * @throws DOMException
	 */
	public function AddBuyerParty (string $endpointId, string $schemeId, string $companyName, string $street, string $city, string $postalCode, string $countryCode, string $taxNumber, string $vatIdentificationNumber, string $contactName, string $telephone, string $email): void {
		$this->AddParty (self::PARTY_BUYER, $endpointId, $schemeId, $companyName, $street, $city, $postalCode, $countryCode, $taxNumber, $vatIdentificationNumber, $contactName, $telephone, $email);
	}

	/**
	 * @param string $actualDeliveryDate In format YYYY-MM-DD
	 *
	 * @return void
	 * @throws DOMException
	 */
	public function AddDelivery (string $actualDeliveryDate): void {

		//Check correct format
		if (!preg_match ('/^\d{4}-\d{2}-\d{2}$/', $actualDeliveryDate)) {
			throw new DOMException("Invalid date format. Must be YYYY-MM-DD");
		}

		$delivery = $this->AddElement ($this->invoice, 'cac:Delivery');
		$this->AddElement ($delivery, 'cbc:ActualDeliveryDate', $actualDeliveryDate);
	}

	/**
	 * @param string $paymentMeansCode Use one of the PAYMENT_METHOD_ constants defined in this class
	 * @param string $paymentId The payment ID used as reference text in the bank transfer if needed
	 * @param string $accountName Who is the owner of the account
	 * @param string $iban
	 * @param string $bic
	 *
	 * @return void
	 * @throws DOMException
	 */
	public function AddPaymentMeans (string $paymentMeansCode, string $paymentId, string $accountName, string $iban, string $bic = ''): void {
		$paymentMeans = $this->AddElement ($this->invoice, 'cac:PaymentMeans');
		$this->AddElement ($paymentMeans, 'cbc:PaymentMeansCode', $paymentMeansCode);
		$this->AddElement ($paymentMeans, 'cbc:PaymentID', $paymentId);

		$account = $this->AddElement ($paymentMeans, 'cac:PayeeFinancialAccount');
		$this->AddElement ($account, 'cbc:ID', $iban);
		/*if (!empty($bic)) {
			$this->AddElement ($account, 'cbc:FinancialInstitutionBranch', $bic);
		}*/
		$this->AddElement ($account, 'cbc:Name', $accountName);
	}

	/**
	 * @param float  $taxAmount
	 * @param float  $taxableAmount
	 * @param string $taxCategoryId
	 * @param float  $taxPercent
	 * @param string $taxSchemeId
	 *
	 * @return void
	 * @throws DOMException
	 */
	public function AddTaxTotal (float $taxAmount, float $taxableAmount, string $taxCategoryId, float $taxPercent, string $taxSchemeId): void {
		$taxTotal = $this->AddElement ($this->invoice, 'cac:TaxTotal');
		$this->AddElement ($taxTotal, 'cbc:TaxAmount', self::RoundValue ($taxAmount), ['currencyID' => 'EUR']);

		$taxSubtotal = $this->AddElement ($taxTotal, 'cac:TaxSubtotal');
		$this->AddElement ($taxSubtotal, 'cbc:TaxableAmount', self::RoundValue ($taxableAmount), ['currencyID' => 'EUR']);
		$this->AddElement ($taxSubtotal, 'cbc:TaxAmount', self::RoundValue ($taxAmount), ['currencyID' => 'EUR']);

		$taxCategory = $this->AddElement ($taxSubtotal, 'cac:TaxCategory');
		$this->AddElement ($taxCategory, 'cbc:ID', $taxCategoryId);
		$this->AddElement ($taxCategory, 'cbc:Percent', self::RoundValue ($taxPercent));

		$taxScheme = $this->AddElement ($taxCategory, 'cac:TaxScheme');
		$this->AddElement ($taxScheme, 'cbc:ID', $taxSchemeId);
	}

	/**
	 * @param float $lineTotalNetAmount
	 * @param float $lineTotalGrossAmount
	 * @param float $prepaidAmount
	 *
	 * @return void
	 * @throws DOMException
	 */
	public function AddLegalMonetaryTotal (float $lineTotalNetAmount, float $lineTotalGrossAmount, float $prepaidAmount = 0): void {
		$monetaryTotal = $this->AddElement ($this->invoice, 'cac:LegalMonetaryTotal');
		$this->AddElement ($monetaryTotal, 'cbc:LineExtensionAmount', self::RoundValue ($lineTotalNetAmount), ['currencyID' => 'EUR']);
		$this->AddElement ($monetaryTotal, 'cbc:TaxExclusiveAmount', self::RoundValue ($lineTotalNetAmount), ['currencyID' => 'EUR']);
		$this->AddElement ($monetaryTotal, 'cbc:TaxInclusiveAmount', self::RoundValue ($lineTotalGrossAmount), ['currencyID' => 'EUR']);
		$this->AddElement ($monetaryTotal, 'cbc:PrepaidAmount', self::RoundValue ($prepaidAmount), ['currencyID' => 'EUR']);
		$this->AddElement ($monetaryTotal, 'cbc:PayableAmount', self::RoundValue ($lineTotalGrossAmount), ['currencyID' => 'EUR']);
	}

	/**
	 * @param int    $lineId incremtal line number
	 * @param int    $invoicedQuantity the quantity of the item in the line
	 * @param float  $lineNetAmount
	 * @param string $itemDescription
	 * @param string $itemName
	 * @param string $itemId
	 * @param string $taxCategoryId
	 * @param float  $taxPercent
	 * @param string $taxSchemeId
	 * @param float  $priceAmount
	 * @param int    $baseQuantity
	 *
	 * @return void
	 * @throws DOMException
	 */
	public function AddInvoiceLine (int $lineId, int $invoicedQuantity, float $lineNetAmount, string $itemDescription, string $itemName, string $itemId, string $taxCategoryId, float $taxPercent, string $taxSchemeId, float $priceAmount, int $baseQuantity): void {
		$invoiceLine = $this->AddElement ($this->invoice, 'cac:InvoiceLine');
		$this->AddElement ($invoiceLine, 'cbc:ID', $lineId);
		$this->AddElement ($invoiceLine, 'cbc:InvoicedQuantity', $invoicedQuantity, ['unitCode' => 'C62']);
		$this->AddElement ($invoiceLine, 'cbc:LineExtensionAmount', self::RoundValue ($lineNetAmount), ['currencyID' => 'EUR']);

		$item = $this->AddElement ($invoiceLine, 'cac:Item');
		$this->AddElement ($item, 'cbc:Description', $itemDescription);
		$this->AddElement ($item, 'cbc:Name', $itemName);

		$sellersItemIdentification = $this->AddElement ($item, 'cac:SellersItemIdentification');
		$this->AddElement ($sellersItemIdentification, 'cbc:ID', $itemId);

		$classifiedTaxCategory = $this->AddElement ($item, 'cac:ClassifiedTaxCategory');
		$this->AddElement ($classifiedTaxCategory, 'cbc:ID', $taxCategoryId);
		$this->AddElement ($classifiedTaxCategory, 'cbc:Percent', self::RoundValue ($taxPercent));

		$taxScheme = $this->AddElement ($classifiedTaxCategory, 'cac:TaxScheme');
		$this->AddElement ($taxScheme, 'cbc:ID', $taxSchemeId);

		$price = $this->AddElement ($invoiceLine, 'cac:Price');
		$this->AddElement ($price, 'cbc:PriceAmount', self::RoundValue ($priceAmount), ['currencyID' => 'EUR']);
		$this->AddElement ($price, 'cbc:BaseQuantity', $baseQuantity, ['unitCode' => 'C62']);
	}

	/**
	 * Creates the final XML Dokument and removes (standard = true) empty nodes
	 *
	 * @param bool $removeEmptyNodes
	 *
	 * @return false|string
	 */
	public function CreateXMLDocument (bool $removeEmptyNodes = true): false|string {

		$this->dom->preserveWhiteSpace = false;
		$this->dom->formatOutput       = true;

		if($removeEmptyNodes) {
			$this->RemoveEmptyElements ($this->dom->documentElement);
		}

		return $this->dom->saveXML ();
	}

	#endregion

	#region PRIVATE METHODS

	/**
	 * @param float $value
	 *
	 * @return float
	 */
	private function RoundValue (float $value): float {
		return round ($value, 2);
	}

	/**
	 * @param string $type Use one of the PARTY_ constants defined in this class
	 * @param string $endpointId
	 * @param string $schemeId
	 * @param string $companyName
	 * @param string $street
	 * @param string $city
	 * @param string $postalCode
	 * @param string $countryCode
	 * @param string $taxNumber
	 * @param string $vatIdentificationNumber
	 * @param string $contactName
	 * @param string $telephone
	 * @param string $email
	 *
	 * @return void
	 * @throws DOMException
	 */
	private function AddParty (string $type, string $endpointId, string $schemeId, string $companyName, string $street, string $city, string $postalCode, string $countryCode, string $taxNumber, string $vatIdentificationNumber, string $contactName, string $telephone, string $email): void {
		$partyElement = $this->AddElement ($this->invoice, 'cac:' . $type);
		$party        = $this->AddElement ($partyElement, 'cac:Party');
		$this->AddElement ($party, 'cbc:EndpointID', $endpointId, ['schemeID' => $schemeId]);

		$partyName = $this->AddElement ($party, 'cac:PartyName');
		$this->AddElement ($partyName, 'cbc:Name', $companyName);

		$address = $this->AddElement ($party, 'cac:PostalAddress');
		$this->AddElement ($address, 'cbc:StreetName', $street);
		$this->AddElement ($address, 'cbc:CityName', $city);
		$this->AddElement ($address, 'cbc:PostalZone', $postalCode);

		$country = $this->AddElement ($address, 'cac:Country');
		$this->AddElement ($country, 'cbc:IdentificationCode', $countryCode);

		if (!empty($vatIdentificationNumber)) {
			$taxScheme1 = $this->AddElement ($party, 'cac:PartyTaxScheme');
			$this->AddElement ($taxScheme1, 'cbc:CompanyID', $vatIdentificationNumber);
			$tax1 = $this->AddElement ($taxScheme1, 'cac:TaxScheme');
			$this->AddElement ($tax1, 'cbc:ID', 'VAT');
		}

		if (!empty($taxNumber)) {

			$taxScheme2 = $this->AddElement ($party, 'cac:PartyTaxScheme');
			$this->AddElement ($taxScheme2, 'cbc:CompanyID', $taxNumber);
			$tax2 = $this->AddElement ($taxScheme2, 'cac:TaxScheme');
			$this->AddElement ($tax2, 'cbc:ID', 'FC');
		}

		$legalEntity = $this->AddElement ($party, 'cac:PartyLegalEntity');
		$this->AddElement ($legalEntity, 'cbc:RegistrationName', $companyName);

		$contact = $this->AddElement ($party, 'cac:Contact');
		$this->AddElement ($contact, 'cbc:Name', $contactName);
		$this->AddElement ($contact, 'cbc:Telephone', $telephone);
		$this->AddElement ($contact, 'cbc:ElectronicMail', $email);
	}

	/**
	 * @param DOMElement $node the highest node from where we start recursively removing empty nodes
	 *
	 * @return void
	 */
	private function RemoveEmptyElements (DOMElement $node): void {

		if ($node->nodeType == XML_ELEMENT_NODE) {
			if (!$node->hasChildNodes ()) {
				if (trim ($node->textContent) === '') {
					$node->parentNode->removeChild ($node);
				}
			} else {
				foreach (iterator_to_array ($node->childNodes) as $child) {
					$this->RemoveEmptyElements ($child);
				}
			}
		}

	}

	/**
	 * @param DOMElement  $parentNode
	 * @param string      $nodeName
	 * @param string|null $value
	 * @param array       $attributes
	 *
	 * @return DOMElement|false
	 * @throws DOMException
	 */
	private function AddElement (DOMElement $parentNode, string $nodeName, string $value = null, array $attributes = []): false|DOMElement {
		$element = $this->dom->createElement ($nodeName, htmlspecialchars ($value));
		foreach ($attributes as $attrName => $attrValue) {
			$element->setAttribute ($attrName, $attrValue);
		}
		$parentNode->appendChild ($element);
		return $element;
	}

	#endregion

}
