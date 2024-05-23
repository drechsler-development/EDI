<?php

namespace EDI\ZUGFeRD;

use DateTime;
use DateTimeZone;
use Easybill\ZUGFeRD\Model\Date;
use Easybill\ZUGFeRD\Model\Document;
use Easybill\ZUGFeRD\Model\Note;
use const DD\EDI\SALESORDER_CANCELLED;

class Invoice {

	private bool $validate;

	private Document $doc;

	public function __construct (bool $validate = true) {

		$this->validate = $validate;

	}

	/**
	 * @throws \Exception
	 */
	public function CreateXML (Document $type, int $invoiceType) {

		$this->doc = new Document($type);

		$this->doc->getHeader ()
		    ->setId ($rechnungsNummer)
		    ->setName ($header->statusId == SALESORDER_CANCELLED ? 'GUTSCHRIFT' : 'RECHNUNG')
		    ->setDate (new Date(new DateTime($date, new DateTimeZone('UTC'))))
		    ->addNote (new Note($header->comment))
		;

	}

}
