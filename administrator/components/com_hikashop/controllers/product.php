<?php
/**
 * @package	HikaShop for Joomla!
 * @version	3.2.1
 * @author	hikashop.com
 * @copyright	(C) 2010-2017 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><?php
class ProductController extends hikashopController {
	var $toggle = array('product_published' => 'product_id');
	var $type ='product';
	var $pkey = 'product_category_id';
	var $main_pkey = 'product_id';
	var $table = 'product_category';
	var $groupMap = 'category_id';
	var $orderingMap ='ordering';
	var $groupVal = 0;

	function __construct($config = array()) {
		parent::__construct($config);
		$this->display = array(
			'unpublish','publish',
			'listing','show','cancel',
			'selectcategory','addcategory',
			'selectrelated','addrelated',
			'form_price_edit',
			'getprice','addimage','selectimage','addfile','selectfile','file_entry',
			'variant','updatecart','export',
			'galleryimage','galleryselect',
			'selection','useselection',
			'getTree','findTree',''
		);
		$this->modify = array_merge($this->modify, array(
			'managevariant', 'variants', 'save_translation', 'copy', 'characteristic'
		));
		$this->modify_views = array_merge($this->modify_views, array(
			'edit_translation', 'priceaccess', 'unpublish', 'publish'
		));

		if(hikaInput::get()->getInt('variant'))
			$this->publish_return_view = 'variant';
	}

	function edit(){
		hikaInput::get()->set('hidemainmenu',1);
		hikaInput::get()->set('layout', 'form');
		if(hikaInput::get()->getInt('legacy', 0) == 1)
			hikaInput::get()->set('layout', 'form_legacy');
		return $this->display();
	}

	function priceaccess(){
		hikaInput::get()->set('layout', 'priceaccess');
		return parent::display();
	}

	function form_price_edit(){
		hikaInput::get()->set('layout', 'form_price_edit');
		parent::display();
		exit;
	}

	function edit_translation(){
		hikaInput::get()->set('layout', 'edit_translation');
		if(hikaInput::get()->getInt('legacy', 0) == 1)
			hikaInput::get()->set('layout', 'edit_translation_legacy');
		return parent::display();
	}

	function save_translation(){
		$product_id = hikashop_getCID('product_id');
		$productClass = hikashop_get('class.product');
		$element = $productClass->get($product_id);
		if(!empty($element->product_id)){
			$translationHelper = hikashop_get('helper.translation');
			$translationHelper->getTranslations($element);
			$translationHelper->handleTranslations('product',$element->product_id,$element);
		}
		$document= JFactory::getDocument();
		$document->addScriptDeclaration('window.top.hikashop.closeBox();');
	}

	function managevariant() {
		$id = $this->store();
		if($id) {
			hikaInput::get()->set('cid',$id);
			$this->variant();
		} else {
			$this->edit();
		}
	}

	function updatecart() {
		$product_id = hikaInput::get()->getInt('cid');
		$productClass = hikashop_get('class.product');
		$product = $productClass->get($product_id);
		echo '<textarea style="width:100%" rows="5"><a class="hikashop_html_add_to_cart_link" href="'. hikashop_contentLink('product&task=updatecart&qty=1&cid='.$product_id, $product, false, false, false, true).'">'.JText::_('ADD_TO_CART').'</a></textarea>';
	}

	function save() {
		$result = parent::store();
		if(!$result)
			return $this->edit();

		if(hikaInput::get()->getBool('variant')) {
			hikaInput::get()->set('cid', hikaInput::get()->getInt('parent_id'));
			$this->variant();
		} else {
			$this->listing();
		}
	}

	function save2new() {
		$result = $this->store(true);
		if($result)
			hikaInput::get()->set('product_id', 0);
		return $this->edit();
	}

	function copy() {
		$products = hikaInput::get()->get('cid', array(), 'array');
		$result = true;
		if(!empty($products)) {
			$importHelper = hikashop_get('helper.import');
			foreach($products as $product) {
				if(!$importHelper->copyProduct($product))
					$result = false;
			}
		}
		if($result) {
			$app = JFactory::getApplication();
			if(!HIKASHOP_J30)
				$app->enqueueMessage(JText::_( 'HIKASHOP_SUCC_SAVED' ), 'success');
			else
				$app->enqueueMessage(JText::_( 'HIKASHOP_SUCC_SAVED' ));
		}
		return $this->listing();
	}

	function variant() {
		hikashop_nocache();
		hikaInput::get()->set('layout', 'variant');

		$legacy = hikaInput::get()->getInt('legacy', 0);
		if($legacy)
			hikaInput::get()->set('layout', 'variant_legacy');

		if(hikaInput::get()->getCmd('tmpl', '') == 'component') {
			ob_end_clean();
			parent::display();
			exit;
		}
		return parent::display();
	}

	public function variants() {
		hikashop_nocache();
		hikaInput::get()->set('layout', 'form_variants');

		$product_id = hikaInput::get()->getInt('product_id', 0);
		$subtask = hikaInput::get()->getCmd('subtask', '');
		if(!empty($subtask)) {
			switch($subtask) {
				case 'setdefault':
					$variant_id = hikaInput::get()->getInt('variant_id');
					$productClass = hikashop_get('class.product');
					$ret = $productClass->setDefaultVariant($product_id, $variant_id);
					break;

				case 'publish':
					$variant_id = hikaInput::get()->getInt('variant_id');
					$productClass = hikashop_get('class.product');
					$ret = $productClass->publishVariant($variant_id);
					break;

				case 'add':
				case 'duplicate':
					if(!HIKASHOP_J25) {
						JRequest::checkToken('request') || die('Invalid Token');
					} else {
						JSession::checkToken('request') || die('Invalid Token');
					}
					hikaInput::get()->set('layout', 'form_variants_add');
					break;

				case 'delete';
					if(!HIKASHOP_J25) {
						JRequest::checkToken('request') || die('Invalid Token');
					} else {
						JSession::checkToken('request') || die('Invalid Token');
					}
					$cid = hikaInput::get()->get('cid', array(), 'array');
					if(empty($cid)) {
						ob_end_clean();
						echo '0';
						exit;
					}
					$productClass = hikashop_get('class.product');
					$ret = $productClass->deleteVariants($product_id, $cid);
					ob_end_clean();
					if($ret !== false)
						echo $ret;
					else
						echo '0';
					exit;

				case 'populate':
					if(!HIKASHOP_J25) {
						JRequest::checkToken('request') || die('Invalid Token');
					} else {
						JSession::checkToken('request') || die('Invalid Token');
					}
					hikaInput::get()->set('layout', 'form_variants_add');

					$productClass = hikashop_get('class.product');
					$data = hikaInput::get()->get('data', array(), 'array');
					if(isset($data['variant_duplicate'])) {
						$cid = hikaInput::get()->get('cid', array(), 'array');
						JArrayHelper::toInteger($cid);
						$ret = $productClass->duplicateVariant($product_id, $cid, $data);
					} else
						$ret = $productClass->populateVariant($product_id, $data);
					if($ret !== false) {
						ob_end_clean();
						echo $ret;
						exit;
					}
					break;
			}
		}

		if(hikaInput::get()->getCmd('tmpl', '') == 'component') {
			ob_end_clean();
			parent::display();
			exit;
		}
		return parent::display();
	}

	public function characteristic() {
		if( !hikashop_acl('product/edit/variants'))
			return false;

		$product_id = hikashop_getCID('product_id');
		$subtask = hikaInput::get()->getCmd('subtask', '');
		if(empty($subtask)) {
		}

		$productClass = hikashop_get('class.product');
		switch($subtask) {
			case 'add':
				if(!HIKASHOP_J25) {
					JRequest::checkToken() || die('Invalid Token');
				} else {
					JSession::checkToken() || die('Invalid Token');
				}
				$characteristic_id = hikaInput::get()->getInt('characteristic_id', 0);
				$characteristic_value_id = hikaInput::get()->getInt('characteristic_value_id', 0);
				$ret = $productClass->addCharacteristic($product_id, $characteristic_id, $characteristic_value_id);
				ob_end_clean();
				if($ret === false)
					echo '-1';
				else
					echo (int)$ret;
				exit;

			case 'remove':
				if(!HIKASHOP_J25) {
					JRequest::checkToken() || die('Invalid Token');
				} else {
					JSession::checkToken() || die('Invalid Token');
				}
				$characteristic_id = hikaInput::get()->getInt('characteristic_id', 0);
				$ret = $productClass->removeCharacteristic($product_id, $characteristic_id);
				ob_end_clean();
				if($ret === false)
					echo '-1';
				else
					echo (int)$ret;
				exit;
		}
		exit;
	}

	function export(){
		hikaInput::get()->set('layout', 'export');
		return parent::display();
	}

	function orderdown(){
		$this->getGroupVal();
		return parent::orderdown();
	}

	function orderup(){
		$this->getGroupVal();
		return parent::orderup();
	}

	function saveorder(){
		$this->getGroupVal();
		return parent::saveorder();
	}

	function getGroupVal(){
		$app = JFactory::getApplication();
		$this->groupVal = $app->getUserStateFromRequest( HIKASHOP_COMPONENT.'.product.filter_id','filter_id',0,'string');
		if(!is_numeric($this->groupVal)){
			$categoryClass = hikashop_get('class.category');
			$categoryClass->getMainElement($this->groupVal);
		}
	}

	function selectcategory(){
		hikaInput::get()->set('layout', 'selectcategory');
		return parent::display();
	}

	function addcategory(){
		hikaInput::get()->set('layout', 'addcategory');
		return parent::display();
	}

	function selectrelated(){
		hikaInput::get()->set('layout', 'selectrelated');
		return parent::display();
	}

	function addrelated(){
		hikaInput::get()->set('layout', 'addrelated');
		return parent::display();
	}

	function addimage(){
		if($this->_saveFile())
			hikaInput::get()->set('layout', 'addimage');
		else
			hikaInput::get()->set('layout', 'selectimage');
		return parent::display();
	}

	function selectimage(){
		hikaInput::get()->set('layout', 'selectimage');
		return parent::display();
	}

	function addfile(){
		$ret = $this->_saveFile();
		if($ret)
			hikaInput::get()->set('layout', 'addfile');
		else
			hikaInput::get()->set('layout', 'selectfile');
		return parent::display();
	}

	function getSizeFile($url) {
		if(substr($url, 0, 4) != 'http')
			return @filesize($url);

		static $regex = '/^Content-Length: *+\K\d++$/im';
		if(!$fp = @fopen($url, 'rb'))
			return false;

		if( isset($http_response_header) && preg_match($regex, implode("\n", $http_response_header), $matches))
			return (int)$matches[0];
		return strlen(stream_get_contents($fp));
	}

	function _saveFile() {
		$file = new stdClass();
		$file->file_id = hikashop_getCID('file_id');
		$formData = hikaInput::get()->get('data', array(), 'array');
		foreach($formData['file'] as $column => $value){
			hikashop_secureField($column);
			$file->$column = strip_tags($value);
		}
		unset($file->file_path);

		$filemode = 'upload';
		if(!empty($formData['filemode']))
			$filemode = $formData['filemode'];
		if(!empty($file->file_id))
			$filemode = null;

		$fileClass = hikashop_get('class.file');
		hikaInput::get()->set('cid', 0);

		switch($filemode) {
			case 'upload':
				if(empty($file->file_id)) {
					$ids = $fileClass->storeFiles($file->file_type,$file->file_ref_id);
					if(is_array($ids)&&!empty($ids)) {
						$file->file_id = array_shift($ids);
						if(isset($file->file_path))
							unset($file->file_path);
					} else
						return false;
				}
				break;

			case 'path':
			default:
				if(isset($formData['filepath']))
					$file->file_path = trim($formData['filepath']);
				if(isset($formData['file']['file_path']))
					$file->file_path = trim($formData['file']['file_path']);

				$config = hikashop_config();
				$store_locally = $config->get('store_external_files_locally',0);
				if(isset($formData['download']))
					$store_locally = $formData['download'];
				if($store_locally && empty($file->file_id) && (substr($file->file_path, 0, 7) == 'http://' || substr($file->file_path, 0, 8) == 'https://')) {
					$parts = explode('/',$file->file_path);
					$name = array_pop($parts);
					$secure_path = $fileClass->getPath($file->file_type);
					if(!file_exists($secure_path.$name)) {
						$data = @file_get_contents($file->file_path);
						if(empty($data)) {
							$app = JFactory::getApplication();
							$app->enqueueMessage('The file could not be retrieved.');
							return false;
						}
						JFile::write($secure_path . $name, $data);
					} else {
						$size = $this->getSizeFile($file->file_path);
						if($size != filesize($secure_path . $name)) {
							$name = $size . '_' . $name;
							if(!file_exists($secure_path.$name))
								JFile::write($secure_path.$name,file_get_contents($file));
						}
					}

					$file->file_path = $name;
				}
				break;
		}

		if(isset($file->file_path)) {
			$app = JFactory::getApplication();
			if(strpos($file->file_path, '..') !== false) {
				$app->enqueueMessage('Invalid data', 'error');
				return false;
			}

			$firstChar = substr($file->file_path, 0, 1);
			$isVirtual = in_array($firstChar, array('#', '@'));
			$isLink = (substr($file->file_path, 0, 7) == 'http://' || substr($file->file_path, 0, 8) == 'https://');

			if(!$isLink && !$isVirtual) {
				$app = JFactory::getApplication();
				$config = hikashop_config();

				if($firstChar == '/' || preg_match('#:[\/\\\]{1}#', $file->file_path)) {
					$clean_filename = JPath::clean($file->file_path);
					$secure_path = $fileClass->getPath($file->file_type);

					if((JPATH_ROOT != '') && strpos($clean_filename, JPath::clean(JPATH_ROOT)) !== 0 && strpos($clean_filename, JPath::clean($secure_path)) !== 0) {
						$app->enqueueMessage('The file path you entered is an absolute path but it is outside of your upload folder: '.JPath::clean($secure_path), 'error');
						return false;
					}

					if(!file_exists($file->file_path)) {
						$app->enqueueMessage('The file path you entered is an absolute path but it doesn\'t exist.', 'error');
						return false;
					}
				} else {
					$secure_path = $fileClass->getPath($file->file_type);
					$clean_filename = JPath::clean($secure_path . '/' . $file->file_path);
					if(!JFile::exists($clean_filename) && (JPATH_ROOT == '' || !JFile::exists(JPATH_ROOT . DS . $clean_filename))) {
						$app->enqueueMessage('File does not exists', 'error');
						return false;
					}
				}
			}
		}

		if(isset($file->file_ref_id) && empty($file->file_ref_id))
			unset($file->file_ref_id);

		if(isset($file->file_limit)) {
			$limit = (int)$file->file_limit;
			if($limit == 0 && $file->file_limit !== 0 && $file->file_limit != '0')
				$file->file_limit = -1;
			else
				$file->file_limit = $limit;
		}

		JPluginHelper::importPlugin('hikashop');
		$dispatcher = JDispatcher::getInstance();
		$do = true;
		$dispatcher->trigger('onHikaBeforeFileSave', array(&$file, &$do));

		if(!$do)
			return false;

		if(empty($file->file_path) && empty($file->file_id)) {
			return false;
		}

		$status = $fileClass->save($file);
		if(empty($file->file_id)) {
			$file->file_id = $status;
		}
		hikaInput::get()->set('cid',$file->file_id);

		$dispatcher->trigger('onHikaAfterFileSave', array(&$file));

		return true;
	}

	function selectfile(){
		hikaInput::get()->set('layout', 'selectfile');
		return parent::display();
	}

	function galleryimage() {
		hikaInput::get()->set('layout', 'galleryimage');
		return parent::display();
	}

	public function file_entry() {
		if( !hikashop_acl('product/edit') )
			return false; // hikashop_deny('product', JText::sprintf('HIKAM_ACTION_DENY', JText::_('HIKAM_ACT_PRODUCT_EDIT')));
		hikaInput::get()->set('layout', 'form_file_entry');
		parent::display();
		exit;
	}

	function galleryselect(){
		$formData = hikaInput::get()->get('data', array(), 'array');
		$filesData = hikaInput::get()->get('files', array(), 'array');

		$fileClass = hikashop_get('class.file');
		$file = new stdClass();
		foreach($formData['file'] as $column => $value){
			hikashop_secureField($column);
			$file->$column = strip_tags($value);
		}
		$file->file_path = reset($filesData);
		if(isset($file->file_ref_id) && empty($file->file_ref_id)){
			unset($file->file_ref_id);
		}
		$status = $fileClass->save($file);
		if(empty($file->file_id)) {
			$file->file_id = $status;
		}
		hikaInput::get()->set('cid', $file->file_id);

		hikaInput::get()->set('layout', 'addimage');
		return parent::display();
	}

	function getprice(){
		$price = hikaInput::get()->getVar('price');
		$price=hikashop_toFloat($price);
		$tax_id = hikaInput::get()->getInt('tax_id');
		$conversion = hikaInput::get()->getInt('conversion');
		$currency = hikaInput::get()->getInt('currency');
		$currencyClass = hikashop_get('class.currency');
		$config =& hikashop_config();
		$main_tax_zone = explode(',',$config->get('main_tax_zone',1346));
		$newprice = $price;
		if(count($main_tax_zone)&&!empty($tax_id)&&!empty($price)&&!empty($main_tax_zone)){
			$function = 'getTaxedPrice';
			if($conversion) {
				$function = 'getUntaxedPrice';
			}
			$newprice = $currencyClass->$function($price,array_shift($main_tax_zone),$tax_id,5);
		}
		if($currency){
			$newprice = $currencyClass->format($newprice,$currency);
		}
		echo $newprice;
		exit;
	}

	function remove(){
		$cids = hikaInput::get()->get('cid', array(), 'array');
		$variant = hikaInput::get()->getInt( 'variant' );
		$class = hikashop_get('class.'.$this->type);
		$num = $class->delete($cids);
		$app = JFactory::getApplication();
		$app->enqueueMessage(JText::sprintf('SUCC_DELETE_ELEMENTS',$num), 'message');
		if($variant){
			hikaInput::get()->set('cid',hikaInput::get()->getInt('parent_id'));
			return $this->variant();
		}
		return $this->listing();
	}

	function selection() {
		hikaInput::get()->set('layout', 'selection');
		return parent::display();
	}

	function useselection() {
		hikaInput::get()->set('layout', 'useselection');
		return parent::display();
	}

	public function getUploadSetting($upload_key, $caller = '') {
		if( !hikashop_acl('product/edit') )
			return false;

		$product_id = hikaInput::get()->getInt('product_id', 0);
		if(empty($upload_key))
			return false;

		$upload_value = null;
		$upload_keys = array(
			'product_image' => array(
				'type' => 'image',
				'view' => 'form_image_entry',
				'file_type' => 'product',
			),
			'product_file' => array(
				'type' => 'file',
				'view' => 'form_file_entry',
				'file_type' => 'file'
			),
		);

		if(empty($upload_keys[$upload_key]))
			return false;
		$upload_value = $upload_keys[$upload_key];

		$config = hikashop_config(false);

		$options = array();
		if($upload_value['type'] == 'image') {
			$options['upload_dir'] = $config->get('uploadfolder');
			$options['processing'] = 'resize';
		} else
			$options['upload_dir'] = $config->get('uploadsecurefolder');

		$options['max_file_size'] = null;

		$product_type = hikaInput::get()->getCmd('product_type', 'product');
		if(!in_array($product_type, array('product','variant')))
			$product_type = 'product';

		return array(
			'limit' => 1,
			'type' => $upload_value['type'],
			'layout' => 'product',
			'view' => $upload_value['view'],
			'options' => $options,
			'extra' => array(
				'product_id' => $product_id,
				'file_type' => $upload_value['file_type'],
				'product_type' => $product_type
			)
		);
	}

	public function manageUpload($upload_key, &$ret, $uploadConfig, $caller = '') {
		if(empty($ret))
			return;

		$config = hikashop_config();
		$product_id = (int)$uploadConfig['extra']['product_id'];

		$file_type = 'product';
		if(!empty($uploadConfig['extra']['file_type']))
			$file_type = $uploadConfig['extra']['file_type'];

		$sub_folder = '';
		if(!empty($uploadConfig['options']['sub_folder']))
			$sub_folder = str_replace('\\', '/', $uploadConfig['options']['sub_folder']);

		if($file_type == 'product')
			$ret->params->product_type = hikaInput::get()->getCmd('product_type', 'product');

		if($caller == 'upload' || $caller == 'addimage') {
			$file = new stdClass();
			$file->file_description = '';
			$file->file_name = $ret->name;
			$file->file_type = $file_type;
			$file->file_ref_id = $product_id;
			$file->file_path = $sub_folder.$ret->name;

			if(strpos($file->file_name, '.') !== false) {
				$file->file_name = substr($file->file_name, 0, strrpos($file->file_name, '.'));
			}

			if($file_type != 'product') {
				$file->file_free_download = $config->get('upload_file_free_download', false);
				$file->file_limit = 0;
			}

			$fileClass = hikashop_get('class.file');
			$status = $fileClass->save($file, $file_type);

			$ret->file_id = $status;
			$ret->params->file_id = $status;

			if($file_type != 'product') {
				$ret->params->file_free_download = $file->file_free_download;
				$ret->params->file_limit = $file->file_limit;
				$ret->params->file_size = @filesize($uploadConfig['upload_dir'] . @$uploadConfig['options']['sub_folder'] . $file->file_name);
			}

			return;
		}

		if($caller == 'galleryselect') {
			$file = new stdClass();
			$file->file_type = 'product';
			$file->file_ref_id = $product_id;
			$file->file_path = $sub_folder.$ret->name;

			$fileClass = hikashop_get('class.file');
			$status = $fileClass->save($file);

			$ret->file_id = $status;
			$ret->params->file_id = $status;

			return;
		}
	}

	function getTree() {
		hikashop_nocache();
		hikashop_cleanBuffers();

		$category_id = hikaInput::get()->getInt('category_id', 0);
		$displayFormat = hikaInput::get()->getVar('displayFormat', '');
		$variants = hikaInput::get()->getInt('variants', 0);
		$search = hikaInput::get()->getVar('search', null);

		$nameboxType = hikashop_get('type.namebox');
		$options = array(
			'start' => $category_id,
			'displayFormat' => $displayFormat,
			'variants' => $variants
		);
		$ret = $nameboxType->getValues($search, $this->type, $options);
		if(!empty($ret)) {
			echo json_encode($ret);
			exit;
		}
		echo '[]';
		exit;
	}
	function findTree() { return $this->getTree(); }
}
