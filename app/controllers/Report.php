<?php
/**
 * Created by PhpStorm.
 * User: philips
 * Date: 16/12/6
 * Time: 下午6:00
 */

defined('BASEPATH') OR exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
require APPPATH . '/libraries/REST_Controller.php';

class Report extends REST_Controller
{
    function __construct()
    {
        parent::__construct();
        $this->load->model('Utility_model','utility');
        $this->load->add_package_path(APPPATH.'third_party');
        $this->load->library('PHPExcel');
        $this->load->library('PHPExcel/IOFactory');
    }

    /**
     * 礼盒库存查询接口
     */
    public function index_get()
    {

    }

    /**
     * 礼盒库存查询接口
     */
    public function giftStock_get()
    {
        $code = 1;
        $message = '礼盒库存查询成功';
        header("Access-Control-Allow-Origin: *");
        $sku = $this->get('sku');
        $boxName = $this->get('boxName');
        $barCode = $this->get('barCode');
        $pageSize= $this->get('pageSize');
        $pageNum = $this->get('pageNum');
        $operator = $this->get('operator');


        // 验证参数
        if(!$this->_checkReportParam($operator))
        {
            return;
        }

        $queryData = array(
            'sku'       =>  $sku,
            'boxName'   =>  $boxName,
            'barCode'   =>  $barCode,
            'pageSize'  =>  (!$pageSize) ? DEFAULT_PAGE_SIZE : $pageSize,
            'pageNum'   =>  (!$pageNum) ?  DEFAULT_PAGE_NUM : $pageNum,
            'operator'  =>  $operator
        );

        // 处理
        $data = $this->_queryGiftStock($queryData);
        $out = array(
            'code'  =>  $code,
            'msg'   =>  $message,
            'data'  =>  json_encode($data),
        );
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    /**
     * 验证礼盒库存查询参数
     * @param $operator
     * @return bool
     */
    protected function _checkReportParam($operator)
    {
        // 参数操作人验证
        if(is_null($operator))
        {
            $this->returnError('操作人不能为空');
            return false;
        }
        return true;
    }


    /**
     * 处理礼盒库存查询
     * @param $queryData
     * @param $allFlg
     * @return array
     */
    protected function _queryGiftStock($queryData,$allFlg=false)
    {
        // 获取查询结果中的礼盒sku的总个数
        $totalNum = $this->_getGiftStockBoxSkuCount($queryData);

        // 获取查询结果中库存的总个数
        $totalQty = 0;

        // 获取查询结果中的礼盒sku列表
        $skuList = $this->_getGiftStockBoxSkuList($queryData,$allFlg);
        // 没有sku命中,直接返回空
        if(!$skuList) {
            return null;
        }
        $this->db->select('box_master.sku,box_master.name,inventory.barcode,inventory.qty');
        $this->_whereGiftStock($queryData,true);
        $this->_sqlGiftStock();
        $this->db->group_by('inventory.barcode');
        $this->db->where_in('box_master.sku', $skuList);
        $query = $this->db->get('inventory');

        $prevSku='';
        $prevName = '';
        $skuQty = 0;
        $detail = array();
        $giftStockInfo = array();
        foreach($query->result() as $row)
        {
            // sku切换时初始化变量
            if($prevSku =='')
            {
                $prevSku = $row->sku;
                $prevName = $row->name;
                $skuQty = 0;
                $detail = array();
            }
            else if($row->sku != $prevSku)
            {
                $skuStockInfo = array(
                    'sku'       =>  $prevSku,
                    'name'      =>  $prevName,
                    'qty'       =>  $skuQty,
                    'detail'    =>  $detail,
                );
                $giftStockInfo[] = $skuStockInfo;
                $prevSku = $row->sku;
                $prevName = $row->name;
                $skuQty = 0;
                $detail = array();
            }

            // 添加明细,更新sku总库存
            $detailInfo = array(
                'barCode'   =>  $row->barcode,
                'qty'       =>  $row->qty,
            );
            $detail[] = $detailInfo;
            $skuQty += $row->qty;
            $totalQty += $row->qty;
        }

        // 加入最后一条数据
        $skuStockInfo = array(
            'sku'       =>  $prevSku,
            'name'      =>  $prevName,
            'qty'       =>  $skuQty,
            'detail'    =>  $detail,
        );
        $giftStockInfo[] = $skuStockInfo;

        $result = array(
            'totalNum'      => $totalNum,
            'totalQty'      => $totalQty,
            'giftStockInfo' => $giftStockInfo,
        );
        return $result;
    }



    /**
     * 获取查询结果中的礼盒sku的总个数
     * @param $queryData
     * @return int
     */
    private function _getGiftStockBoxSkuCount($queryData)
    {
        $this->db->select('box_master.sku');
        $this->_whereGiftStock($queryData,true);
        $this->_sqlGiftStock();
        $this->db->group_by('box_master.sku');
        $query = $this->db->get('inventory');
        return $query->num_rows();
    }



    /**
     * 获取查询结果中的礼盒sku列表
     * @param $queryData
     * @param bool $allFlg
     * @return array
     */
    private function _getGiftStockBoxSkuList($queryData,$allFlg=false)
    {
        $result = array();
        $this->db->select('box_master.sku');
        $this->_whereGiftStock($queryData,$allFlg);
        $this->_sqlGiftStock();
        $this->db->group_by('box_master.sku');
        $query = $this->db->get('inventory');
        foreach($query->result() as $row)
        {
            $result[] = $row->sku;
        }

        return $result;
    }

    /**
     * 礼盒库存报表查询语句
     */
    private function _sqlGiftStock()
    {
        $this->db->join('box_pattern','box_pattern.barcode = inventory.barcode');
        $this->db->join('box_master','box_master.id = box_pattern.box_id');
    }

    /**
     * 筛选礼盒库存查询条件
     * @param $queryData
     * @param $allFlg
     * @return array
     */
    private function _whereGiftStock($queryData,$allFlg=false)
    {
        if($queryData['sku'])
        {
            $this->db->like('box_master.sku', $queryData['sku']);
        }
        if($queryData['boxName'])
        {
            $this->db->like('box_master.name', $queryData['boxName']);
        }
        if($queryData['barCode'])
        {
            $this->db->like('box_pattern.barcode', $queryData['barCode']);
        }
        if(!$allFlg)
        {
            $this->db->limit($queryData['pageSize'] , ($queryData['pageNum'] -1) * $queryData['pageSize']);
        }
    }

    /**
     * 批量导出礼盒库存接口
     */
    public function giftStockToXls_get()
    {
        header("Access-Control-Allow-Origin: *");
        $sku = $this->get('sku');
        $boxName = $this->get('boxName');
        $barCode = $this->get('barCode');
        $operator = $this->get('operator');

        // 验证参数
        if(!$this->_checkReportParam($operator))
        {
            return;
        }

        $queryData = array(
            'sku'       =>  $sku,
            'boxName'   =>  $boxName,
            'barCode'   =>  $barCode,
            'pageSize'  =>  DEFAULT_PAGE_SIZE,
            'pageNum'   =>  DEFAULT_PAGE_NUM ,
            'operator'  =>  $operator,
        );

        // 处理
        $data = $this->_queryGiftStock($queryData,true);

        // 导出excel文件
        $this->_exportGiftStockToXls($data);
    }

    /**
     * 导出礼盒库存列表文件
     * @param $data
     */
    protected function _exportGiftStockToXls($data)
    {
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->getProperties()->setTitle("export")->setDescription("none");
        // 创建第一个SHEET,设置title为查询报表项
        $currentSheet=$objPHPExcel->setactivesheetindex(0);
        $currentSheet->setTitle('giftStock');
        $currentSheet->getCell('a1')->setValue("礼盒SKU");
        $currentSheet->getCell('b1')->setValue("礼盒总数量");
        $currentSheet->getCell('c1')->setValue("礼盒名称");
        $currentSheet->getCell('d1')->setValue("礼盒条码");
        $currentSheet->getCell('e1')->setValue("礼盒个数");
        if($data)
        {
            $lineNo = 2;
            $mergeFrom = 0;
            $mergeTo = 0;
            foreach($data['giftStockInfo'] as $k=>$row)
            {
                $mergeFrom = $lineNo;
                $sku  = $row['sku'];
                $name = $row['name'];
                $qty = $row['qty'];
                $currentSheet->setCellValueExplicit('a'.$lineNo,$sku,PHPExcel_Cell_DataType::TYPE_STRING);
                $currentSheet->getCell('b'.$lineNo)->setValue($qty);
                $currentSheet->setCellValueExplicit('c'.$lineNo,$name,PHPExcel_Cell_DataType::TYPE_STRING);


                foreach($row['detail'] as $dk=>$detail)
                {
                    $objPHPExcel->getActiveSheet()->setCellValueExplicit('d'.$lineNo,$detail['barCode'],PHPExcel_Cell_DataType::TYPE_STRING);
                    $currentSheet->getCell('e'.$lineNo)->setValue($detail['qty']);
                    $lineNo ++;
                }
                $mergeTo = $lineNo - 1;
                $currentSheet->mergeCells('a'. $mergeFrom.':a'. $mergeTo);
                $currentSheet->mergeCells('b'. $mergeFrom.':b'. $mergeTo);
                $currentSheet->mergeCells('c'. $mergeFrom.':c'. $mergeTo);
            }
//            $currentSheet->getColumnDimension('a')->setAutoSize(true);
//            $currentSheet->getColumnDimension('b')->setAutoSize(true);
            $currentSheet->getColumnDimension('c')->setAutoSize(true);
            $currentSheet->getColumnDimension('d')->setAutoSize(true);
            $currentSheet->getColumnDimension('e')->setAutoSize(true);
        }

        $objWriter = IOFactory::createWriter($objPHPExcel, 'Excel5');
        //发送标题强制用户下载文件
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="giftStock'.date("YmdHis",time()).'.xls"');
        header('Cache-Control: max-age=0');
        $objWriter->save('php://output');
    }

    /**
     * 组品库存查询接口
     */
    public function productStock_get()
    {
        $code = 1;
        $message = '组品库存查询成功';
        header("Access-Control-Allow-Origin: *");
        $boxSku = $this->get('boxSku');
        $boxName = $this->get('boxName');
        $productSku = $this->get('productSku');
        $barCode = $this->get('barCode');
        $productName = $this->get('productName');
        $pageSize= $this->get('pageSize');
        $pageNum = $this->get('pageNum');
        $operator = $this->get('operator');

        // 验证参数
        if(!$this->_checkReportParam($operator))
        {
            return;
        }

        $queryData = array(
            'boxSku'        =>  $boxSku,
            'boxName'       =>  $boxName,
            'productSku'    =>  $productSku,
            'barCode'       =>  $barCode,
            'productName'   =>  $productName,
            'pageSize'      =>  (!$pageSize) ? DEFAULT_PAGE_SIZE : $pageSize,
            'pageNum'       =>  (!$pageNum) ?  DEFAULT_PAGE_NUM : $pageNum,
            'operator'      =>  $operator
        );

        // 处理
        $data = $this->_queryProductStock($queryData);
        $out = array(
            'code'  =>  $code,
            'msg'   =>  $message,
            'data'  =>  json_encode($data),
        );
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }


    /**
     * 处理礼盒库存查询
     * @param $queryData
     * @param $allFlg
     * @return array
     */
    protected function _queryProductStock($queryData,$allFlg=false)
    {
        // 获取查询结果中的礼盒sku的总个数
        $totalNum = $this->_getProductStockBoxSkuCount($queryData);

        // 获取查询结果中库存的总个数
        $totalQty = 0;

        // 获取查询结果中的礼盒sku列表
        $skuList = $this->_getProductStockBoxSkuList($queryData,$allFlg);
        // 没有sku命中,直接返回空
        if(!$skuList) {
            return null;
        }
        $this->db->select('box_master.sku as boxSku,box_master.name as boxName,material_master.sku as prodSku,material_master.barcode,material_master.name as prodName,inventory.qty');
        $this->_sqlProductStock();
        $this->_whereProductStock($queryData,true);
        $this->db->where_in('box_master.sku', $skuList);
        $query = $this->db->get('inventory');

        $prevBoxSku='';
        $prevBoxName = '';
        $detail = array();
        $productStockInfo = array();
        foreach($query->result() as $row)
        {
            // sku切换时初始化变量
            if($prevBoxSku =='')
            {
                $prevBoxSku = $row->boxSku;
                $prevBoxName = $row->boxName;
                $detail = array();
            }
            else if($row->boxSku != $prevBoxSku)
            {
                $skuStockInfo = array(
                    'boxSku'       =>  $prevBoxSku,
                    'boxName'      =>  $prevBoxName,
                    'detail'    =>  $detail,
                );
                $productStockInfo[] = $skuStockInfo;
                $prevBoxSku = $row->boxSku;
                $prevBoxName = $row->boxName;
                $detail = array();
            }

            // 添加明细,更新sku总库存
            $detailInfo = array(
                'productSku'    =>  $row->prodSku,
                'barCode'       =>  $row->barcode,
                'productName'   =>  $row->prodName,
                'qty'           =>  $row->qty,
            );
            $detail[] = $detailInfo;
            $totalQty += $row->qty;
        }

        // 加入最后一条数据
        $skuStockInfo = array(
            'boxSku'       =>  $prevBoxSku,
            'boxName'      =>  $prevBoxName,
            'detail'    =>  $detail,
        );
        $productStockInfo[] = $skuStockInfo;

        $result = array(
            'totalNum'          => $totalNum,
            'totalQty'          =>  $totalQty,
            'productStockInfo'  => $productStockInfo,
        );
        return $result;
    }

    /**
     * 获取查询结果中的礼盒sku的总个数
     * @param $queryData
     * @return int
     */
    private function _getProductStockBoxSkuCount($queryData)
    {
        $this->db->select('box_master.sku');
        $this->_whereProductStock($queryData,true);
        $this->_sqlProductStock();
        $this->db->group_by('box_master.sku');
        $query = $this->db->get('inventory');
        return $query->num_rows();
    }

    /**
     * 获取查询结果中的礼盒sku列表
     * @param $queryData
     * @param bool $allFlg
     * @return array
     */
    private function _getProductStockBoxSkuList($queryData,$allFlg=false)
    {
        $result = array();
        $this->db->select('box_master.sku');
        $this->_whereProductStock($queryData,$allFlg);
        $this->_sqlProductStock();
        $this->db->group_by('box_master.sku');
        $query = $this->db->get('inventory');
        foreach($query->result() as $row)
        {
            $result[] = $row->sku;
        }
        return $result;
    }

    /**
     * 商品库存报表查询语句
     */
    private function _sqlProductStock()
    {
        $this->db->join('box_pattern','box_pattern.barcode = inventory.barcode');
        $this->db->join('box_master','box_master.id = box_pattern.box_id');
        $this->db->join('material_master','material_master.id = box_pattern.product_id');
    }

    /**
     * 筛选商品库存查询条件
     * @param $queryData
     * @param $allFlg
     * @return array
     */
    private function _whereProductStock($queryData,$allFlg=false)
    {
        if($queryData['boxSku'])
        {
            $this->db->like('box_master.sku', $queryData['boxSku']);
        }
        if($queryData['boxName'])
        {
            $this->db->like('box_master.name', $queryData['boxName']);
        }
        if($queryData['productSku'])
        {
            $this->db->like('material_master.sku', $queryData['productSku']);
        }
        if($queryData['barCode'])
        {
            $this->db->like('material_master.barcode', $queryData['barCode']);
        }
        if($queryData['productName'])
        {
            $this->db->like('material_master.sku', $queryData['productName']);
        }
        if(!$allFlg)
        {
            $this->db->limit($queryData['pageSize'] , ($queryData['pageNum'] -1) * $queryData['pageSize']);
        }
    }

    /**
     * 批量导出商品库存接口
     */
    public function productStockToXls_get()
    {
        header("Access-Control-Allow-Origin: *");
        $boxSku = $this->get('boxSku');
        $boxName = $this->get('boxName');
        $productSku = $this->get('productSku');
        $barCode = $this->get('barCode');
        $productName = $this->get('productName');
        $pageSize= $this->get('pageSize');
        $pageNum = $this->get('pageNum');
        $operator = $this->get('operator');

        // 验证参数
        if(!$this->_checkReportParam($operator))
        {
            return;
        }

        $queryData = array(
            'boxSku'        =>  $boxSku,
            'boxName'       =>  $boxName,
            'productSku'    =>  $productSku,
            'barCode'       =>  $barCode,
            'productName'   =>  $productName,
            'pageSize'      =>  (!$pageSize) ? DEFAULT_PAGE_SIZE : $pageSize,
            'pageNum'       =>  (!$pageNum) ?  DEFAULT_PAGE_NUM : $pageNum,
            'operator'      =>  $operator
        );

        // 处理
        $data = $this->_queryProductStock($queryData,true);

        // 导出excel文件
        $this->_exportProductStockToXls($data);
    }

    /**
     * 导出商品库存列表文件
     * @param $data
     */
    protected function _exportProductStockToXls($data)
    {
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->getProperties()->setTitle("export")->setDescription("none");
        // 创建第一个SHEET,设置title为查询报表项
        $currentSheet=$objPHPExcel->setactivesheetindex(0);
        $currentSheet->setTitle('productStock');
        $currentSheet->getCell('a1')->setValue("礼盒SKU");
        $currentSheet->getCell('b1')->setValue("礼盒名称");
        $currentSheet->getCell('c1')->setValue("商品SKU");
        $currentSheet->getCell('d1')->setValue("商品条码");
        $currentSheet->getCell('e1')->setValue("商品名称");
        $currentSheet->getCell('f1')->setValue("在库数量");

        if ($data)
        {
            $lineNo = 2;
            $mergeFrom = 0;
            $mergeTo = 0;
            foreach($data['productStockInfo'] as $k=>$row)
            {
                $mergeFrom = $lineNo;
                $boxSku  = $row['boxSku'];
                $boxName = $row['boxName'];
                $currentSheet->setCellValueExplicit('a'.$lineNo,$boxSku,PHPExcel_Cell_DataType::TYPE_STRING);
                $currentSheet->setCellValueExplicit('b'.$lineNo,$boxName,PHPExcel_Cell_DataType::TYPE_STRING);

                foreach($row['detail'] as $dk=>$detail)
                {
                    $currentSheet->setCellValueExplicit('c'.$lineNo,$detail['productSku'],PHPExcel_Cell_DataType::TYPE_STRING);
                    $currentSheet->setCellValueExplicit('d'.$lineNo,$detail['barCode'],PHPExcel_Cell_DataType::TYPE_STRING);
                    $currentSheet->setCellValueExplicit('e'.$lineNo,$detail['productName'],PHPExcel_Cell_DataType::TYPE_STRING);
                    $currentSheet->getCell('f'.$lineNo)->setValue($detail['qty']);
                    $lineNo ++;
                }
                $mergeTo = $lineNo - 1;
                $currentSheet->mergeCells('a'. $mergeFrom.':a'. $mergeTo);
                $currentSheet->mergeCells('b'. $mergeFrom.':b'. $mergeTo);
            }
            $currentSheet->getColumnDimension('a')->setAutoSize(true);
            $currentSheet->getColumnDimension('b')->setAutoSize(true);
            $currentSheet->getColumnDimension('c')->setAutoSize(true);
            $currentSheet->getColumnDimension('d')->setAutoSize(true);
            $currentSheet->getColumnDimension('e')->setAutoSize(true);
            $currentSheet->getColumnDimension('f')->setAutoSize(true);
        }
        $objPHPExcel->setActiveSheetIndex(0);
        $objWriter = IOFactory::createWriter($objPHPExcel, 'Excel5');
        //发送标题强制用户下载文件
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="productStock'.date("YmdHis",time()).'.xls"');
        header('Cache-Control: max-age=0');
        $objWriter->save('php://output');
    }

    /**
     * 出库统计查询接口
     */
    public function outStock_get()
    {
        $code = 1;
        $message = '出库统计查询成功';
        header("Access-Control-Allow-Origin: *");
        $fromDate = $this->get('fromDate');
        $toDate = $this->get('toDate');
        $pageSize= $this->get('pageSize');
        $pageNum = $this->get('pageNum');
        $operator = $this->get('operator');

        // 验证参数
        if(!$this->_checkReportParam($operator))
        {
            return;
        }

        $queryData = array(
            'fromDate'      =>  $fromDate,
            'toDate'        =>  $toDate,
            'pageSize'      =>  (!$pageSize) ? DEFAULT_PAGE_SIZE : $pageSize,
            'pageNum'       =>  (!$pageNum) ?  DEFAULT_PAGE_NUM : $pageNum,
            'operator'      =>  $operator
        );

        // 处理
        $data = $this->_queryOutStock($queryData);
        $out = array(
            'code'  =>  $code,
            'msg'   =>  $message,
            'data'  =>  json_encode($data),
        );
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    /**
     *出库记录查询
     * @param $queryData
     * @param $allFlg
     * @return array
     */
    protected function _queryOutStock($queryData,$allFlg=false)
    {
        // 获取查询结果中的礼盒sku的总个数
        $totalNum = $this->_getOutStockBoxSkuCount($queryData);

        // 获取查询结果中库存的总个数
        $totalQty = 0;

        // 获取查询结果中的礼盒sku列表
        $skuList = $this->_getOutStockBoxSkuList($queryData,$allFlg);
        // 没有sku命中,直接返回空
        if(!$skuList) {
            return null;
        }
        $this->db->select('box_master.sku as boxSku,box_master.name as boxName,material_master.sku as prodSku,material_master.barcode,material_master.name as prodName,sum(movement_history.qty) as qty');
        $this->_whereOutStock($queryData,true);
        $this->_sqlOutStock();
        $this->db->where_in('box_master.sku',$skuList);
        $this->db->group_by('material_master.sku');
        $this->db->order_by('movement_history.created_at','DESC');
        $query = $this->db->get('movement_history');

        $prevBoxSku='';
        $prevBoxName = '';
        $detail = array();
        $outStockInfo = array();
        foreach($query->result() as $row)
        {
            // sku切换时初始化变量
            if($prevBoxSku =='')
            {
                $prevBoxSku = $row->boxSku;
                $prevBoxName = $row->boxName;
                $detail = array();
            }
            else if($row->boxSku != $prevBoxSku)
            {
                $skuStockInfo = array(
                    'boxSku'       =>  $prevBoxSku,
                    'boxName'      =>  $prevBoxName,
                    'detail'    =>  $detail,
                );
                $outStockInfo[] = $skuStockInfo;
                $prevBoxSku = $row->boxSku;
                $prevBoxName = $row->boxName;
                $detail = array();
            }

            // 添加明细,更新sku总库存
            $detailInfo = array(
                'productSku'    =>  $row->prodSku,
                'barCode'       =>  $row->barcode,
                'productName'   =>  $row->prodName,
                'qty'           =>  $row->qty,
            );
            $detail[] = $detailInfo;
            $totalQty += $row->qty;
        }

        // 加入最后一条数据
        $skuStockInfo = array(
            'boxSku'       =>  $prevBoxSku,
            'boxName'      =>  $prevBoxName,
            'detail'    =>  $detail,
        );
        $outStockInfo[] = $skuStockInfo;

        $result = array(
            'totalNum'          => $totalNum,
            'totalQty'          =>  $totalQty,
            'outStockInfo'  => $outStockInfo,
        );
        return $result;
    }

    /**
     * 获取查询结果中的礼盒sku的总个数
     * @param $queryData
     * @return int
     */
    private function _getOutStockBoxSkuCount($queryData)
    {
        $result = array();
        $this->db->select('box_master.sku');
        $this->_whereOutStock($queryData,true);
        $this->_sqlOutStock();
        $this->db->group_by('box_master.sku');
        $query = $this->db->get('movement_history');
        return $query->num_rows();
    }

    /**
     * 获取查询结果中的礼盒sku列表
     * @param $queryData
     * @param bool $allFlg
     * @return array
     */
    private function _getOutStockBoxSkuList($queryData,$allFlg=false)
    {
        $result = array();
        $this->db->select('box_master.sku');
        $this->_whereOutStock($queryData,$allFlg);
        $this->_sqlOutStock();
        $this->db->group_by('box_master.sku');
        $this->db->order_by('movement_history.created_at','DESC');
        $query = $this->db->get('movement_history');
        foreach($query->result() as $row)
        {
            $result[] = $row->sku;
        }
        return $result;
    }

    /**
     * 商品库存报表查询语句
     */
    private function _sqlOutStock()
    {
        $this->db->join('box_pattern','box_pattern.barcode = movement_history.barcode');
        $this->db->join('box_master','box_master.id = box_pattern.box_id');
        $this->db->join('material_master','material_master.id = box_pattern.product_id');
    }

    /**
     * 筛选出库记录查询条件
     * @param $queryData
     * @param $allFlg
     * @return array
     */
    private function _whereOutStock($queryData,$allFlg=false)
    {
        $this->db->where('movement_history.type', MOVEMENT_TYOE_OUT);
        if($queryData['fromDate'])
        {
            $this->db->where('movement_history.created_at >=', $queryData['fromDate']);
        }
        if($queryData['toDate'])
        {
            $this->db->where('movement_history.created_at <=', $queryData['toDate']);
        }
        if(!$allFlg)
        {
            $this->db->limit($queryData['pageSize'] , ($queryData['pageNum'] -1) * $queryData['pageSize']);
        }
    }


    /**
     * 批量导出库记录接口
     */
    public function outStockToXls_get()
    {
        header("Access-Control-Allow-Origin: *");
        $fromDate = $this->get('fromDate');
        $toDate = $this->get('toDate');
        $pageSize= $this->get('pageSize');
        $pageNum = $this->get('pageNum');
        $operator = $this->get('operator');

        // 验证参数
        if(!$this->_checkReportParam($operator))
        {
            return;
        }

        $queryData = array(
            'fromDate'      =>  $fromDate,
            'toDate'        =>  $toDate,
            'pageSize'      =>  (!$pageSize) ? DEFAULT_PAGE_SIZE : $pageSize,
            'pageNum'       =>  (!$pageNum) ?  DEFAULT_PAGE_NUM : $pageNum,
            'operator'      =>  $operator
        );

        // 处理
        $data = $this->_queryOutStock($queryData,true);

        // 导出excel文件
        $this->_exportOutStockToXls($data);
    }

    /**
     * 导出出库记录文件
     * @param $data
     */
    protected function _exportOutStockToXls($data)
    {
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->getProperties()->setTitle("export")->setDescription("none");
        // 创建第一个SHEET,设置title为查询报表项
        $currentSheet=$objPHPExcel->setactivesheetindex(0);
        $currentSheet->setTitle('outStock');
        $currentSheet->getCell('a1')->setValue("礼盒SKU");
        $currentSheet->getCell('b1')->setValue("礼盒名称");
        $currentSheet->getCell('c1')->setValue("商品SKU");
        $currentSheet->getCell('d1')->setValue("商品条码");
        $currentSheet->getCell('e1')->setValue("商品名称");
        $currentSheet->getCell('f1')->setValue("出库数量");

        if($data) {
            $lineNo = 2;
            $mergeFrom = 0;
            $mergeTo = 0;
            foreach ($data['outStockInfo'] as $k => $row) {
                $mergeFrom = $lineNo;
                $boxSku = $row['boxSku'];
                $boxName = $row['boxName'];
                $currentSheet->setCellValueExplicit('a' . $lineNo, $boxSku, PHPExcel_Cell_DataType::TYPE_STRING);
                $currentSheet->setCellValueExplicit('b' . $lineNo, $boxName, PHPExcel_Cell_DataType::TYPE_STRING);

                foreach ($row['detail'] as $dk => $detail) {
                    $currentSheet->setCellValueExplicit('c' . $lineNo, $detail['productSku'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $currentSheet->setCellValueExplicit('d' . $lineNo, $detail['barCode'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $currentSheet->setCellValueExplicit('e' . $lineNo, $detail['productName'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $currentSheet->getCell('f' . $lineNo)->setValue($detail['qty']);
                    $lineNo++;
                }
                $mergeTo = $lineNo - 1;
                $currentSheet->mergeCells('a' . $mergeFrom . ':a' . $mergeTo);
                $currentSheet->mergeCells('b' . $mergeFrom . ':b' . $mergeTo);
            }
            $currentSheet->getColumnDimension('a')->setAutoSize(true);
            $currentSheet->getColumnDimension('b')->setAutoSize(true);
            $currentSheet->getColumnDimension('c')->setAutoSize(true);
            $currentSheet->getColumnDimension('d')->setAutoSize(true);
            $currentSheet->getColumnDimension('e')->setAutoSize(true);
            $currentSheet->getColumnDimension('f')->setAutoSize(true);
        }
        $objPHPExcel->setActiveSheetIndex(0);
        $objWriter = IOFactory::createWriter($objPHPExcel, 'Excel5');
        //发送标题强制用户下载文件
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="outStock'.date("YmdHis",time()).'.xls"');
        header('Cache-Control: max-age=0');
        $objWriter->save('php://output');
    }


    /**
     * 入库统计查询接口
     */
    public function inStock_get()
    {
        $code = 1;
        $message = '入库统计查询成功';
        header("Access-Control-Allow-Origin: *");
        $fromDate = $this->get('fromDate');
        $toDate = $this->get('toDate');
        $pageSize= $this->get('pageSize');
        $pageNum = $this->get('pageNum');
        $operator = $this->get('operator');

        // 验证参数
        if(!$this->_checkReportParam($operator))
        {
            return;
        }

        $queryData = array(
            'fromDate'      =>  $fromDate,
            'toDate'        =>  $toDate,
            'pageSize'      =>  (!$pageSize) ? DEFAULT_PAGE_SIZE : $pageSize,
            'pageNum'       =>  (!$pageNum) ?  DEFAULT_PAGE_NUM : $pageNum,
            'operator'      =>  $operator
        );

        // 处理
        $data = $this->_queryInStock($queryData);
        $out = array(
            'code'  =>  $code,
            'msg'   =>  $message,
            'data'  =>  json_encode($data),
        );
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    /**
     * 入库记录查询
     * @param $queryData
     * @param $allFlg
     * @return array
     */
    protected function _queryInStock($queryData,$allFlg=false)
    {
        // 获取查询结果中的礼盒sku的总个数
        $totalNum = $this->_getInStockBoxSkuCount($queryData);

        // 获取查询结果中库存的总个数
        $totalQty = 0;


        // 获取查询结果中的礼盒sku列表
        $skuList = $this->_getInStockBoxSkuList($queryData,$allFlg);
        // 没有sku命中,直接返回空
        if(!$skuList) {
            return null;
        }
        $this->db->select('box_master.sku as boxSku,box_master.name as boxName,material_master.sku as prodSku,material_master.barcode,material_master.name as prodName,sum(movement_history.qty) as qty');
        $this->_whereInStock($queryData,true);
        $this->_sqlInStock();
        $this->db->where_in('box_master.sku',$skuList);
        $this->db->group_by('material_master.sku');
        $this->db->order_by('movement_history.created_at','DESC');
        $query = $this->db->get('movement_history');

        $prevBoxSku='';
        $prevBoxName = '';
        $detail = array();
        $inStockInfo = array();
        foreach($query->result() as $row)
        {
            // sku切换时初始化变量
            if($prevBoxSku =='')
            {
                $prevBoxSku = $row->boxSku;
                $prevBoxName = $row->boxName;
                $detail = array();
            }
            else if($row->boxSku != $prevBoxSku)
            {
                $skuStockInfo = array(
                    'boxSku'       =>  $prevBoxSku,
                    'boxName'      =>  $prevBoxName,
                    'detail'    =>  $detail,
                );
                $inStockInfo[] = $skuStockInfo;
                $prevBoxSku = $row->boxSku;
                $prevBoxName = $row->boxName;
                $detail = array();
            }

            // 添加明细,更新sku总库存
            $detailInfo = array(
                'productSku'    =>  $row->prodSku,
                'barCode'       =>  $row->barcode,
                'productName'   =>  $row->prodName,
                'qty'           =>  $row->qty,
            );
            $detail[] = $detailInfo;
            $totalQty += $row->qty;
        }

        // 加入最后一条数据
        $skuStockInfo = array(
            'boxSku'       =>  $prevBoxSku,
            'boxName'      =>  $prevBoxName,
            'detail'       =>  $detail,
        );
        $inStockInfo[] = $skuStockInfo;

        $result = array(
            'totalNum'          => $totalNum,
            'totalQty'          =>  $totalQty,
            'inStockInfo'       => $inStockInfo,
        );
        return $result;
    }

    /**
     * 获取查询结果中的礼盒sku的总个数
     * @param $queryData
     * @return int
     */
    private function _getInStockBoxSkuCount($queryData)
    {
        $this->db->select('box_master.sku');
        $this->_whereInStock($queryData,true);
        $this->_sqlInStock();
        $this->db->group_by('box_master.sku');
        $query = $this->db->get('movement_history');
        return $query->num_rows();
    }

    /**
     * 获取查询结果中的礼盒sku列表
     * @param $queryData
     * @param bool $allFlg
     * @return array
     */
    private function _getInStockBoxSkuList($queryData,$allFlg=false)
    {
        $result = array();
        $this->db->select('box_master.sku');
        $this->_whereInStock($queryData,$allFlg);
        $this->_sqlInStock();
        $this->db->group_by('box_master.sku');
        $this->db->order_by('movement_history.created_at','DESC');
        $query = $this->db->get('movement_history');
        foreach($query->result() as $row)
        {
            $result[] = $row->sku;
        }

        return $result;
    }

    /**
     * 入库记录查询语句
     */
    private function _sqlInStock()
    {
        $this->db->join('box_pattern','box_pattern.barcode = movement_history.barcode');
        $this->db->join('box_master','box_master.id = box_pattern.box_id');
        $this->db->join('material_master','material_master.id = box_pattern.product_id');
    }

    /**
     * 筛选入库记录查询条件
     * @param $queryData
     * @param $allFlg
     * @return array
     */
    private function _whereInStock($queryData,$allFlg=false)
    {
        $this->db->where('movement_history.type', MOVEMENT_TYOE_IN);
        if($queryData['fromDate'])
        {
            $this->db->where('movement_history.created_at >=', $queryData['fromDate']);
        }
        if($queryData['toDate'])
        {
            $this->db->where('movement_history.created_at <=', $queryData['toDate']);
        }
        if(!$allFlg)
        {
            $this->db->limit($queryData['pageSize'] , ($queryData['pageNum'] -1) * $queryData['pageSize']);
        }
    }


    /**
     * 批量导出入库记录接口
     */
    public function inStockToXls_get()
    {
        header("Access-Control-Allow-Origin: *");
        $fromDate = $this->get('fromDate');
        $toDate = $this->get('toDate');
        $pageSize= $this->get('pageSize');
        $pageNum = $this->get('pageNum');
        $operator = $this->get('operator');

        // 验证参数
        if(!$this->_checkReportParam($operator))
        {
            return;
        }

        $queryData = array(
            'fromDate'      =>  $fromDate,
            'toDate'        =>  $toDate,
            'pageSize'      =>  (!$pageSize) ? DEFAULT_PAGE_SIZE : $pageSize,
            'pageNum'       =>  (!$pageNum) ?  DEFAULT_PAGE_NUM : $pageNum,
            'operator'      =>  $operator
        );

        // 处理
        $data = $this->_queryInStock($queryData,true);

        // 导出excel文件
        $this->_exportInStockToXls($data);
    }

    /**
     * 导出入库记录文件
     * @param $data
     */
    protected function _exportInStockToXls($data)
    {
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->getProperties()->setTitle("export")->setDescription("none");
        // 创建第一个SHEET,设置title为查询报表项
        $currentSheet=$objPHPExcel->setactivesheetindex(0);
        $currentSheet->setTitle('inStock');
        $currentSheet->getCell('a1')->setValue("礼盒SKU");
        $currentSheet->getCell('b1')->setValue("礼盒名称");
        $currentSheet->getCell('c1')->setValue("商品SKU");
        $currentSheet->getCell('d1')->setValue("商品条码");
        $currentSheet->getCell('e1')->setValue("商品名称");
        $currentSheet->getCell('f1')->setValue("入库数量");

        if($data)
        {
            $lineNo = 2;
            $mergeFrom = 0;
            $mergeTo = 0;
            foreach($data['inStockInfo'] as $k=>$row)
            {
                $mergeFrom = $lineNo;
                $boxSku  = $row['boxSku'];
                $boxName = $row['boxName'];
                $currentSheet->setCellValueExplicit('a'.$lineNo,$boxSku,PHPExcel_Cell_DataType::TYPE_STRING);
                $currentSheet->setCellValueExplicit('b'.$lineNo,$boxName,PHPExcel_Cell_DataType::TYPE_STRING);

                foreach($row['detail'] as $dk=>$detail)
                {
                    $currentSheet->setCellValueExplicit('c'.$lineNo,$detail['productSku'],PHPExcel_Cell_DataType::TYPE_STRING);
                    $currentSheet->setCellValueExplicit('d'.$lineNo,$detail['barCode'],PHPExcel_Cell_DataType::TYPE_STRING);
                    $currentSheet->setCellValueExplicit('e'.$lineNo,$detail['productName'],PHPExcel_Cell_DataType::TYPE_STRING);
                    $currentSheet->getCell('f'.$lineNo)->setValue($detail['qty']);
                    $lineNo ++;
                }
                $mergeTo = $lineNo - 1;
                $currentSheet->mergeCells('a'. $mergeFrom.':a'. $mergeTo);
                $currentSheet->mergeCells('b'. $mergeFrom.':b'. $mergeTo);
            }
            $currentSheet->getColumnDimension('a')->setAutoSize(true);
            $currentSheet->getColumnDimension('b')->setAutoSize(true);
            $currentSheet->getColumnDimension('c')->setAutoSize(true);
            $currentSheet->getColumnDimension('d')->setAutoSize(true);
            $currentSheet->getColumnDimension('e')->setAutoSize(true);
            $currentSheet->getColumnDimension('f')->setAutoSize(true);
        }

        $objPHPExcel->setActiveSheetIndex(0);
        $objWriter = IOFactory::createWriter($objPHPExcel, 'Excel5');
        //发送标题强制用户下载文件
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="inStock'.date("YmdHis",time()).'.xls"');
        header('Cache-Control: max-age=0');
        $objWriter->save('php://output');
    }
}
