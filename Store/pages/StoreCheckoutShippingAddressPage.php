<?php

require_once 'Store/pages/StoreCheckoutEditPage.php';
require_once 'Swat/SwatYUI.php';

/**
 * Shipping address edit page of checkout
 *
 * @package   Store
 * @copyright 2005-2007 silverorange
 */
class StoreCheckoutShippingAddressPage extends StoreCheckoutEditPage
{
	// {{{ protected properties

	/**
	 * @var StoreOrderAddress
	 *
	 * @see StoreCheckoutShippingAddress::getShippingAddress()
	 */
	protected $shipping_address;

	// }}}
	// {{{ public function __construct()

	public function __construct(SiteAbstractPage $page)
	{
		parent::__construct($page);
		$this->ui_xml = 'Store/pages/checkout-shipping-address.xml';
	}

	// }}}

	// init phase
	// {{{ public function initCommon()

	public function initCommon()
	{
		parent::initCommon();

		// default country flydown to the country of the current locale
		$country_flydown = $this->ui->getWidget('shipping_address_country');
		$country_flydown->value = $this->app->getCountry();
	}

	// }}}

	// process phase
	// {{{ public function preProcessCommon()

	public function preProcessCommon()
	{
		$address_list = $this->ui->getWidget('shipping_address_list');
		$address_list->process();

		if ($address_list->value === null || $address_list->value === 'new') {
			if ($this->ui->getWidget('form')->isSubmitted())
				$this->setupPostalCode();
		} else {
			$container = $this->ui->getWidget('shipping_address_form');
			$controls = $container->getDescendants('SwatInputControl');
			foreach ($controls as $control)
				$control->required = false;
		}
	}

	// }}}
	// {{{ public function processCommon()

	public function processCommon()
	{
		// if form validated, perform additional checks on generated address
		// object. This is dependent on Billing Address already having been
		// saved to the session, so we can't perform this check in
		// validateCommon
		if (!$this->ui->getWidget('form')->hasMessage())
			$this->validateShippingAddress();

		// only save address in session if above validation didn't cause other
		// validation messages to be generated.
		if (!$this->ui->getWidget('form')->hasMessage())
			$this->saveDataToSession();
	}

	// }}}
	// {{{ protected function setupPostalCode()

	protected function setupPostalCode()
	{
		// set provsate and country on postal code entry
		$postal_code = $this->ui->getWidget('shipping_address_postalcode');
		$country = $this->ui->getWidget('shipping_address_country');
		$provstate = $this->ui->getWidget('shipping_address_provstate');

		$country->process();
		$provstate->process();

		if ($provstate->value === 'other') {
			$provstate_other =
				$this->ui->getWidget('billing_address_provstate_other');

			$provstate_other->required = true;
			$provstate->value = null;
		}

		if ($provstate->value !== null) {
			$sql = sprintf('select abbreviation from ProvState where id = %s',
			$this->app->db->quote($provstate->value));

			$provstate_abbreviation = SwatDB::queryOne($this->app->db, $sql);
			$postal_code->country = $country->value;
			$postal_code->provstate = $provstate_abbreviation;
		}
	}

	// }}}
	// {{{ protected function saveDataToSession()

	protected function saveDataToSession()
	{
		$this->app->session->order->shipping_address =
			$this->getShippingAddress();
	}

	// }}}
	// {{{ protected function validateShippingAddress()

	protected function validateShippingAddress()
	{
		$address = $this->getShippingAddress();

		$shipping_country_ids = array();
		foreach ($this->app->getRegion()->shipping_countries as $country)
			$shipping_country_ids[] = $country->id;

		if (!in_array($address->getInternalValue('country'),
			$shipping_country_ids)) {
			$field = $this->ui->getWidget('shipping_address_list_field');
			$field->addMessage(new SwatMessage('Orders can not be shipped to '.
				'the country of the selected address. Select a different '.
				'shipping address or enter a new shipping address.'));
		}
	}

	// }}}
	// {{{ protected function getShippingAddress()

	protected function getShippingAddress()
	{
		if ($this->shipping_address instanceof StoreOrderAddress)
			return $this->shipping_address;

		$address_list = $this->ui->getWidget('shipping_address_list');
		$class_name = SwatDBClassMap::get('StoreOrderAddress');
		$address = new $class_name();

		if ($address_list->value === null || $address_list->value === 'new') {
			$address->fullname =
				$this->ui->getWidget('shipping_address_fullname')->value;

			$address->company =
				$this->ui->getWidget('shipping_address_company')->value;

			$address->line1 =
				$this->ui->getWidget('shipping_address_line1')->value;

			$address->line2 =
				$this->ui->getWidget('shipping_address_line2')->value;

			$address->city =
				$this->ui->getWidget('shipping_address_city')->value;

			$address->provstate =
				$this->ui->getWidget('shipping_address_provstate')->value;

			$address->provstate_other =
				$this->ui->getWidget('shipping_address_provstate_other')->value;

			$address->postal_code =
				$this->ui->getWidget('shipping_address_postalcode')->value;

			$address->country =
				$this->ui->getWidget('shipping_address_country')->value;

			$address->phone =
				$this->ui->getWidget('shipping_address_phone')->value;

		} elseif ($address_list->value === 'billing') {
			$address = $this->app->session->order->billing_address;
		} else {
			$address_id = intval($address_list->value);

			$account_address =
				$this->app->session->account->addresses->getByIndex(
				$address_id);

			if (!($account_address instanceof StoreAccountAddress))
				throw new StoreException('Account address not found. Address '.
					"with id ‘{$address_id}’ not found.");

			$address->copyFrom($account_address);
		}

		$this->shipping_address = $address;

		return $this->shipping_address;
	}

	// }}}

	// build phase
	// {{{ public function buildCommon()

	public function buildCommon()
	{
		$this->buildList();
		$this->buildForm();

		if (!$this->ui->getWidget('form')->isProcessed())
			$this->loadDataFromSession();
	}

	// }}}
	// {{{ public function postBuildCommon()

	public function postBuildCommon()
	{
		$this->layout->startCapture('content');
		Swat::displayInlineJavaScript($this->getInlineJavaScript());
		$this->layout->endCapture();
	}

	// }}}
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		parent::buildInternal();

		/*
		 * Set page to two-column layout when page is stand-alone even when
		 * there is no address list. The narrower layout of the form fields
		 * looks better even withour a select list on the left.
		 */
		$this->ui->getWidget('form')->classes[] = 'checkout-no-column';
	}

	// }}}
	// {{{ protected function loadDataFromSession()

	protected function loadDataFromSession()
	{
		$order = $this->app->session->order;

		if ($order->shipping_address === null) {
			$default_address = $this->getDefaultShippingAddress();
			if ($default_address !== null) {
				$this->ui->getWidget('shipping_address_list')->value =
					$default_address->id;
			}
		} else {
			if ($order->shipping_address->getAccountAddressId() === null &&
				$order->shipping_address !== $order->billing_address) {

				$this->ui->getWidget('shipping_address_fullname')->value =
					$order->shipping_address->fullname;

				$this->ui->getWidget('shipping_address_company')->value =
					$order->shipping_address->company;

				$this->ui->getWidget('shipping_address_line1')->value =
					$order->shipping_address->line1;

				$this->ui->getWidget('shipping_address_line2')->value =
					$order->shipping_address->line2;

				$this->ui->getWidget('shipping_address_city')->value =
					$order->shipping_address->city;

				$this->ui->getWidget('shipping_address_provstate')->value =
					$order->shipping_address->getInternalValue('provstate');

				$this->ui->getWidget('shipping_address_provstate_other')->value =
					$order->shipping_address->provstate_other;

				$this->ui->getWidget('shipping_address_postalcode')->value =
					$order->shipping_address->postal_code;

				$this->ui->getWidget('shipping_address_country')->value =
					$order->shipping_address->getInternalValue('country');

				$this->ui->getWidget('shipping_address_phone')->value =
					$order->shipping_address->phone;

				$this->ui->getWidget('shipping_address_list')->value = 'new';

			} else {
				// compare references since these are not saved yet
				if ($order->billing_address === $order->shipping_address) {
					$this->ui->getWidget('shipping_address_list')->value =
						'billing';

				} else {
					$this->ui->getWidget('shipping_address_list')->value =
						$order->shipping_address->getAccountAddressId();
				}
			}
		}
	}

	// }}}
	// {{{ protected function getDefaultShippingAddress()

	protected function getDefaultShippingAddress()
	{
		$address = null;

		if ($this->app->session->isLoggedIn()) {
			$default_address =
				$this->app->session->account->getDefaultShippingAddress();

			if ($default_address !== null) {
				// only default to addresses that actually appear in the list
				$address_list = $this->ui->getWidget('shipping_address_list');
				$options =
					$address_list->getOptionsByValue($default_address->id);

				if (count($options) > 0)
					$address = $default_address;
			}
		}

		return $address;
	}

	// }}}
	// {{{ protected function buildList()

	protected function buildList()
	{
		$address_list = $this->ui->getWidget('shipping_address_list');

		$span = '<span class="add-new">%s</span>';

		// TODO: it is possible to select a billing address that is not
		// shippable and then select "ship to billing address".
		if ($this->app->session->checkout_with_account) {
			$address_list->addOption('new',
				sprintf($span, Store::_('Add a New Address')), 'text/xml');

			$address_list->addOption('billing',
				sprintf($span, Store::_('Ship to Billing Address')),
				'text/xml');

		} else {
			$address_list->addOption('billing',
				sprintf($span, Store::_('Ship to Billing Address')),
				'text/xml');

			$address_list->addOption('new',
				sprintf($span, Store::_('Ship to a Different Address')),
				'text/xml');
		}

		if ($this->app->session->isLoggedIn())
			$this->buildAccountShippingAddresses($address_list);
	}

	// }}}
	// {{{ protected function buildForm()

	protected function buildForm()
	{
		$provstate_flydown = $this->ui->getWidget('shipping_address_provstate');
		$provstate_flydown->addOptionsByArray(SwatDB::getOptionArray(
			$this->app->db, 'ProvState', 'title', 'id', 'title',
				sprintf('country in (select country from
				RegionShippingCountryBinding where region = %s)',
				$this->app->db->quote($this->app->getRegion()->id, 'integer'))));

		$provstate_other = $this->ui->getWidget('shipping_address_provstate_other');
		if ($provstate_other->visible) {
			$provstate_flydown->addDivider();
			$option = new SwatOption('other', 'Other…');
			$provstate_flydown->addOption($option);
		}

		$country_flydown = $this->ui->getWidget('shipping_address_country');
		$country_flydown->addOptionsByArray(SwatDB::getOptionArray(
			$this->app->db, 'Country', 'title', 'id', 'title',
				sprintf('id in (select country from RegionShippingCountryBinding
				where region = %s) and visible = %s',
				$this->app->db->quote($this->app->getRegion()->id, 'integer'),
				$this->app->db->quote(true, 'boolean'))));
	}

	// }}}
	// {{{ protected function buildAccountShippingAddresses()

	protected function buildAccountShippingAddresses(
		SwatOptionControl $address_list)
	{
		$shipping_country_ids = array();
		foreach ($this->app->getRegion()->shipping_countries as $country)
			$shipping_country_ids[] = $country->id;

		foreach ($this->app->session->account->addresses as $address) {
			if (in_array($address->getInternalValue('country'),
				$shipping_country_ids)) {

				ob_start();
				$address->displayCondensed();
				$condensed_address = ob_get_clean();

				$address_list->addOption($address->id, $condensed_address,
					'text/xml');
			}
		}
	}

	// }}}
	// {{{ protected function buildAccountBillingAddressRegionMessage()

	protected function buildAccountBillingAddressRegionMessage(
		SwatContentBlock $content_block)
	{
		// TODO: pull parts of this up from Veseys
	}

	// }}}
	// {{{ protected function getInlineJavaScript()

	protected function getInlineJavaScript()
	{
		$provstate = $this->ui->getWidget('shipping_address_provstate');
		$provstate_other_index = count($provstate->options);
		$id = 'checkout_shipping_address';
		return sprintf(
			"var %s_obj = new StoreCheckoutShippingAddressPage('%s', %s);",
			$id, $id, $provstate_other_index);
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();
		$this->layout->addHtmlHeadEntry(new SwatStyleSheetHtmlHeadEntry(
			'packages/store/styles/store-checkout-address-page.css',
			Store::PACKAGE_ID));

		$yui = new SwatYUI(array('dom', 'event'));
		$this->layout->addHtmlHeadEntrySet($yui->getHtmlHeadEntrySet());

		$path = 'packages/store/javascript/';
		$this->layout->addHtmlHeadEntry(new SwatJavaScriptHtmlHeadEntry(
			$path.'store-checkout-page.js',
			Store::PACKAGE_ID));

		$this->layout->addHtmlHeadEntry(new SwatJavaScriptHtmlHeadEntry(
			$path.'store-checkout-address-page.js',
			Store::PACKAGE_ID));

		$this->layout->addHtmlHeadEntry(new SwatJavaScriptHtmlHeadEntry(
			$path.'store-checkout-shipping-address-page.js',
			Store::PACKAGE_ID));
	}

	// }}}
}

?>
