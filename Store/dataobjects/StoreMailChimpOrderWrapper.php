<?php

require_once 'SwatDB/SwatDBRecordsetWrapper.php';
require_once 'Store/dataobjects/StoreMailChimpOrder.php';

/**
 * A recordset wrapper class for StoreMailChimpOrder objects
 *
 * @package   Store
 * @copyright 2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreMailChimpOrderWrapper extends SwatDBRecordsetWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();

		$this->index_field = 'id';
		$this->row_wrapper_class = SwatDBClassMap::get('StoreMailChimpOrder');
	}

	// }}}
}

?>
