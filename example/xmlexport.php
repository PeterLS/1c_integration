<?php 
class ControllerExtensionModuleXmlexport extends Controller {
	private $error = array();
 
	public function index() {
		$data['success'] = '';
		$data['error_warning'] = '';
		$nums = '';
		if(isset($this->request->get['autoload']) && $this->request->get['autoload'] == "9hah1idIxnSm2Jh2k53UGP4ucCoPfefB") {
			
			$zipfile = $this->getLastFile(DIR_IMPORT, "zip");
			if (!empty($zipfile)) {
			  $zip = new ZipArchive;
			  $sh_zip = $zip->open(DIR_IMPORT.$zipfile);
        if ($sh_zip === true) {
          if ($zip->extractTo(DIR_IMAGE . 'catalog/import/') === TRUE) {
            $zip->close();
            unlink(DIR_IMPORT.$zipfile);
          } else {
            $zip->close();
            die('ZIP extract error.');
          }
        } else {
          die('Error open zip. Error: '.$sh_zip);
        }
			}
			
			$xlsfile = $this->getLastFile(DIR_IMPORT, "xml");
			if($xlsfile) {
				$nums = $this->load(DIR_IMPORT.$xlsfile);
				// unlink($xlsfile);
			}
			return;
		}
		
		$this->load->language('extension/module/xmlexport');
		
		$this->document->setTitle('Выгрузка XML');
		
		$data['action'] = $this->url->link('extension/module/xmlexport', 'token=' . $this->session->data['token'], 'SSL');

		$data['heading_title'] = $this->language->get('heading_title');
		
		$data['heading_title'] = 'Выгрузка XML';
		
		$data['breadcrumbs'] = array();

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),     		
      		'separator' => false
   		);

   		$data['breadcrumbs'][] = array(
       		'text'      => 'Выгрузка XML',
			'href'      => $this->url->link('extension/module/xmlexport', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => ' :: '
   		);
		
		$data['restore'] = $this->url->link('extension/module/xmlexport', 'token=' . $this->session->data['token'], 'SSL');
		$data['loader'] = $this->url->link('extension/module/xmlexport/load', 'token=' . $this->session->data['token'], 'SSL');
		$data['autoloader'] = $this->url->link('extension/module/xmlexport', 'autoload=9hah1idIxnSm2Jh2k53UGP4ucCoPfefB', 'SSL');
		
		
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/xmlexport.tpl', $data));
	}
	
	public function load($filename) {
		ini_set("memory_limit","512M");
		ini_set("max_execution_time",36000);
		
		$rImages = $this->getImages();
		
		$this->load->model('catalog/manufacturer');
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		
		$handle = fopen($filename, "r");
		$contents = fread($handle, filesize($filename));
		fclose($handle);
		$xml = new SimpleXMLElement($contents);
		
		//$this->db->query("UPDATE " . DB_PREFIX . "product SET status=0");
		//$this->db->query("UPDATE " . DB_PREFIX . "category SET status=0");
		
		foreach($xml->Goods->Item as $item) {
			$product = array();
			
			foreach($item->attributes() as $k => $v) {
				$product[$k] = (string)$v;
			}

      $model = $product['code'];
      $dimage = "no_image.jpg";
      $sn = explode('-',$model);
      $imsn = $sn[1]*1;
      if(isset($rImages[$imsn])) {
        if ($product["isactive"] == "false") {
          unlink(DIR_IMAGE . 'catalog/import/' . $rImages[$imsn]);
        } else {
          $dimage = 'catalog/import/'.$rImages[$imsn];
        }
        unset($rImages[$imsn]);
      }

      $product_id = $this->model_catalog_product->getProductByModel($model);
      $prod = $this->model_catalog_product->getProduct($product_id);

      if ($product["isactive"] == "false" && (empty($product_id) || empty($prod['status']))) {
        continue;
      }
			
			$product['filters'] = array();
			
			foreach($item->filters->filter as $filter) {
				$filter = $filter->attributes();
				$product['filters'][(string)$filter['filtername']] = (string)$filter['filtervalue'];
			}
			
			foreach($item->Prices->price as $price) {
				$price = $price->attributes();
				$product[(string)$price['pricename']] = (string)$price['pricevalue'];
			}
				
			$cats = array();
			$main_category_id = 0;
			$l1Category = 0;
			$l2Category = 0;
			$l3Category = 0;
			$l4Category = 0;
			
			$categories = explode('/',$product['category']);
			
			foreach($categories as $cak => $cav) {
				$categories[$cak] = trim($cav);
			}
			
			if(isset($categories[0]) && $categories[0] != "") {
				$l1Category = $this->model_catalog_category->getCategoryByName($categories[0], 0);
				$cats[] = $l1Category['category_id'];
				$main_category_id = $l1Category['category_id'];
			}
			
			if(isset($categories[1]) && $categories[1] != "") {
				$l2Category = $this->model_catalog_category->getCategoryByName($categories[1], $l1Category['category_id']);
				$cats[] = $l2Category['category_id'];
				$main_category_id = $l2Category['category_id'];
			}
			
			if(isset($categories[2]) && $categories[2] != "") {
				$l3Category = $this->model_catalog_category->getCategoryByName($categories[2], $l2Category['category_id']);
				$cats[] = $l3Category['category_id'];
				$main_category_id = $l3Category['category_id'];
			}
			
			if(isset($categories[3]) && $categories[3] != "") {
				$l4Category = $this->model_catalog_category->getCategoryByName($categories[3], $l3Category['category_id']);
				$main_category_id = $l4Category['category_id'];
				$cats[] = $l4Category['category_id'];
			}

			$guid = $product['guid'];
			
			$mpn = $this->model_catalog_product->getMpnByModel($model);

			
			$dimages = array();
			
			$price = (isset($product["Розничная"]) AND $product["showprice"] == "true")? $product["Розничная"]: 0;
			$discount = array();
			if(isset($product["Базовая"])) {
				$discount = array(
					0	=> array(
						"customer_group_id"	=> 2,
						"quantity"			=> 1,
						"priority"			=> 0,
						"price"				=> $product["Базовая"],
						"date_start"		=> "",
						"date_end"			=> ""
					)
				);
			}
			$special = array();
			
			$attrs = array();
			
			foreach($product['filters'] as $kf => $kv) {
				if($kf == "Фильтр по сериям") {
					$attrs[] = array(
						"attribute_id"	=> 12,
						"product_attribute_description"	=> array(
							1	=> array(
								"text"	=> $kv
							)
						)
					);
				}
			
				// if($this->getCell($dataSheet,$i,11)."" != "") {
					// $attrs[] = array(
						// "attribute_id"	=> 12,
						// "product_attribute_description"	=> array(
							// 1	=> array(
								// "text"	=> $this->getCell($dataSheet,$i,11).""
							// )
						// )
					// );
				// }
				
				// if($this->getCell($dataSheet,$i,12)."" != "") {
					// $attrs[] = array(
						// "attribute_id"	=> 13,
						// "product_attribute_description"	=> array(
							// 1	=> array(
								// "text"	=> $this->getCell($dataSheet,$i,12).""
							// )
						// )
					// );
				// }
			}
			
			$data = array(
				"product_id"			=> $product_id,
				"product_description"	=> array(
					1	=> array(
						"name"				=> str_replace('"','',$product["name"]),
						"meta_h1"			=> "", 
						"meta_title"		=> "", 
						"meta_keyword"		=> "", 
						"meta_description"	=> "", 
						"description"		=> str_replace('$','<br><br>',str_replace('"','',$product["description"])),
						"tag"				=> ""
					)
				),
				"model"					=> $model, 
				"sku"					=> $guid,
				"upc"					=> "", 
				"ean"					=> "", 
				"jan"					=> "", 
				"isbn"					=> "", 
				"mpn"					=> $mpn, 
				"location"				=> "", 
				"price"					=> $price, 
				"product_discount"		=> $discount,
				"tax_class_id"			=> "0", 
				"quantity"				=> (isset($product["stock"])? $product["stock"]:0), 
				"minimum"				=> "1", 
				"subtract"				=> "1", 
				"stock_status_id"		=> ((isset($product["stock"]) AND $product["stock"]>0 )? "7":"8"), 
				"shipping"				=> "1", 
				"keyword"				=> "", 
				"image"					=> $dimage, 
				"date_available"		=> date('Y-m-d', time()), 
				"length"				=> "",
				"width"					=> "",
				"height"				=> "",
				"length_class_id"		=> "1", 
				"weight"				=> "",
				"weight_class_id"		=> "1",
				"status"				=> ($product["isactive"] == "true"? 1:0), 
				"sort_order"			=> "200", 
				"manufacturer_id"		=> ($prod["manufacturer_id"]? $prod["manufacturer_id"]:0),
				"main_category_id"		=> $main_category_id, 
				"product_category"		=> $cats,
				"filter"				=> "", 
				"product_store"			=> array(
					0	=> "0"
				),
				"download"				=> "", 
				"related"				=> "",
				"product_attribute"		=> $attrs,
				"option"				=> "",
				"product_special"		=> $special,
				"points"				=> "0",
				"product_reward"		=> array(
					1	=> array(
						"points"	=> "0"
					)
				),
				"product_layout"		=> array(
					0	=> array(
						"layout_id"	=> ""
					)
				),
				"product_image"			=> $dimages,
				'keyword'				=> ''
			);
			
			if($product_id) {
				$this->model_catalog_product->editProduct($product_id, $data);
			} else {
				$this->model_catalog_product->addProduct($data);
			}
		}


    $this->model_catalog_category->checkCategoryStatus();
		
		if(isset($this->session->data['token'])) {
			header( 'Location: http://proftomsk.ru/admin/index.php?route=extension/module/xmlexport&token='.$this->session->data['token'] );
		} else {
			$xmlstr = '<?xml version="1.0" encoding="UTF-8"?><error descr="">0</error>';
			$sxe = new SimpleXMLElement($xmlstr);
			echo $sxe->asXML();
		}
	}
	
	function getCell(&$worksheet,$row,$col,$default_val='') {
		$col -= 1; // we use 1-based, PHPExcel uses 0-based column index
		$row += 1; // we use 0-based, PHPExcel used 1-based row index
		return str_replace('\\','',trim(($worksheet->cellExistsByColumnAndRow($col,$row)) ? $worksheet->getCellByColumnAndRow($col,$row)->getValue() : $default_val));
	}

  public function getImages() {
    $dir = DIR_IMAGE . 'catalog/import/';
    $files = array();
    $yesDir = opendir($dir); // открываем директорию
    $fn = array();
    if (!$yesDir)
      die('Невозможно получить файлы из директории ' . $dir);

    // идем по элементам директории
    while (false !== ($filename = readdir($yesDir))) {
      // пропускаем вложенные папки
      if ($filename == '.' || $filename == '..' || $filename == 'current')
        continue;
      $sn = explode('-', $filename);
      if (isset($sn[1])) {
        $newname = $sn[1];
        rename($dir . $filename, $dir . $newname);
      } else {
        $newname = $filename;
      }

      $fname = explode('.', $newname);
      if (file_exists($dir . $fname[0] . '.jpg') && file_exists($dir . $fname[0] . '.png')) {
        if (filectime($dir . $fname[0] . '.jpg') > filectime($dir . $fname[0] . '.png')) {
          unlink($dir . $fname[0] . '.png');
          $fn[$fname[0] * 1] = $fname[0] . '.jpg';
        } else {
          unlink($dir . $fname[0] . '.jpg');
          $fn[$fname[0] * 1] = $fname[0] . '.png';
        }
      } else {
        $fn[$fname[0] * 1] = $newname;
      }
    }

    return $fn;
  }
	
	public function getLastFile($dir, $type) {
        $files = array();
        $yesDir = opendir($dir); // открываем директорию
		$fn = array();
 
        if (!$yesDir)
            die('Невозможно получить файлы из директории ' . $dir);
 
        // идем по элементам директории
        while (false !== ($filename = readdir($yesDir))) {
            // пропускаем вложенные папки
            if ($filename == '.' || $filename == '..' || $filename == 'current')
            continue;

			$ext = explode('.',$filename);
			if($ext[1] == $type) {
				// получаем время последнего изменения файла, заносим в массивы
				$lastModified = filemtime("$dir/$filename");
				$lm[] = $lastModified;
				$fn[] = $filename;
			}
        }
 
		if($fn) {
			// сортируем массивы имен файлов и времен изменения по возрастанию последнего
			$files = array_multisort($lm,SORT_NUMERIC,SORT_ASC,$fn);
			$last_index = count($lm)-1;
	 
			// форматируем дату и время изменения файла с учетом текущей локали
			$lastTime = strftime ("%k:%M:%S %e %B %Y",$lm[$last_index]);
			return $fn[$last_index];
		} else {
			if(isset($this->session->data['token']) || $type == 'zip') {
				return false;
			} else {
				$xmlstr = '<?xml version="1.0" encoding="UTF-8"?><error descr="Отсутствует файл выгрузки">1</error>';
				$sxe = new SimpleXMLElement($xmlstr);
				echo $sxe->asXML();
				exit();
			}
		}
    }
}
?>