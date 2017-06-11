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


class Task extends REST_Controller
{

    const STATUS_NEW = 0;
    const STATUS_TASK_PRINT = 1;
    const STATUS_CODE_PRINT = 2;
    const STATUS_ALL_PRINT = 3;
    const STATUS_COMPLETE = 4;

    function __construct()
    {
        parent::__construct();
        $this->load->model('Utility_model','utility');
        $this->load->model('Task_model','task');
        $this->load->model('Master_model','master');
        $this->load->add_package_path(APPPATH.'third_party');
        $this->load->library('PHPExcel');
        $this->load->library('PHPExcel/IOFactory');
    }

    public function index_get()
    {
        $code = 0;
        $message = '';
        $data = array();

        header("Access-Control-Allow-Origin: *");
        $pageSize = $this->get('pageSize');
        $pageNum = $this->get('pageNum');

        if($pageSize != ''){
            $limitNum = $pageSize;
        }else{
            $limitNum = 10;
        }
        if ($pageNum != ''){
            $limitStart = ($pageNum - 1) * $limitNum ;
        }else{
            $limitStart = 0;
        }

        $fields = array(
            'taskId'      => $this->get('taskId'),
            'boxName'     => $this->get('boxName'),
            'comment'     => $this->get('comment'),
            'createdFrom' => $this->get('fromDate'),
            'createdTo'   => $this->get('toDate'),
            'status'      => $this->get('status'),
            'creater'     => $this->get('creater'),
            'qty'         => $this->get('qty'),
        );

        $resultList = array();
        $resultList = $this->task->getTaskDetail($fields,$limitNum,$limitStart);
        $resultCount = $this->task->getTaskCount($fields,$limitNum,$limitStart);
        $resultDetail = array();
        $reformatArray = array();
        foreach ($resultList as $k=>$v){
            $reformatArray = array(
                'taskId'     => $v['id'],
                'boxName'    => $v['name'],
                'qty'        => $v['qty'],
                'comment'    => $v['comment'],
                'createdDate'=> $v['created_at'],
                'creater'    => $v['creater'],
                'status'     => $v['status'],
                'deleteFlg'  => $v['del_flg'],
//                'completeFlg'=> $v['com_flg'],
            );
            array_push($resultDetail, $reformatArray);

        }

        $data = array(
            'totalNum' => $resultCount,
            'taskList' => $resultList,
        );

        $result = array(
            'code' => 1,
            'msg' => $message,
            'data' => json_encode($data),
        );
        $this->set_response($result, REST_Controller::HTTP_OK);
    }

    /**
     * 查询组装项明细
     */
    public function detail_get()
    {
        $code = 1;
        $message = '';
        $data = array();
        $itemId = 1;
        header("Access-Control-Allow-Origin: *");
        $taskId = $this->get('taskId');

        if ($data = $this->_processTaskItemInfo($taskId, $itemId)){
            $out = array(
                'code'  =>  $code,
                'msg'   =>  $message,
                'data'  =>  json_encode($data),
            );
        }else {
            $out = array(
                'code' => 0,
                'msg' => '任务组装明细查询失败',
                'data' => json_encode($data),
            );
        }
        $this->set_response($out, REST_Controller::HTTP_OK);
    }

    protected function _processTaskItemInfo($taskId, $itemId)
    {

        $searchResult = $this->task->getTaskItemDetail($taskId);
        $data = array();
        foreach ($searchResult as $k=>$v){

            if ($itemId == $v['item_id']){
                $boxInfo = array(
                    'itemId' => $v['item_id'],
                    $boxItem = array(
                        'sku'     => $v['sku'],
                        'barCode' => $v['barcode'],
                        'qty'     => $v['qty'],
                    )
                );

                array_push($data, $boxInfo);
            }else{
                $itemId = $v['item_id'];
                $boxInfo = array(
                    'itemId' => $itemId,
                    $boxItem = array(
                        'sku'     => $v['sku'],
                        'barCode' => $v['barcode'],
                        'qty'     => $v['qty'],
                    )
                );
                array_push($data, $boxInfo);
            }

        }
        return $data;
    }

    /**
     * 库内作业--查看组装工单
     */
    public function content_get()
    {
        $code = 1;
        $message = '';
        $data = array();

        header("Access-Control-Allow-Origin: *");
        $taskId = $this->get('taskId');


        if ($data = $this->_processContentInfo($taskId)){
            $out = array(
                'code'  =>  $code,
                'msg'   =>  $message,
                'data'  =>  json_encode($data),
            );
        }else {
            $out = array(
                'code' => 0,
                'msg' => '组装工单明细查询失败',
                'data' => json_encode($data),
            );
        }

        $this->set_response($out, REST_Controller::HTTP_OK);
    }

    protected function _processContentInfo($taskId)
    {

        $searchResult = $this->task->getContentDetail($taskId);
        $prevBarCode = '';
        $data = array();
        $list = array();
        $list2= array();
        foreach ($searchResult as $k=>$v){
            $printInfo = array(
                'barCodePrintTime' => $v['barcocde_print_time'],
                'barCodePrintOperator' => $v['barcocde_print_operator'],
                'taskPrintTime' => $v['task_print_time'],
                'taskPrintOperator' => $v['task_print_operator'],
            );
            if ($prevBarCode == $v['barcode']){
                $boxInfo = array(
                    'barCode' => $v['barcode'],
                    'qty'     => $v['qty'],
                    $boxItem = array(
                        'sku'     => $v['sku'],
                        'barCode' => $v['mbarcode'],
                        'name'    => $v['name'],
                        'qty'     => $v['qty'],
                    )

                );
                array_push($list, $boxInfo);
            }else{
                $prevBarCode = $v['barcode'];
                $boxInfo = array(
                    'barCode' => $v['barcode'],
                    'qty'     => $v['qty'],
                    $boxItem = array(
                        'sku'     => $v['sku'],
                        'barCode' => $v['mbarcode'],
                        'name'    => $v['name'],
                        'qty'     => $v['qty'],
                    )
                );
                array_push($list, $boxInfo);
            }

        }
        array_push($list2, $list);
        $data = array_merge($list2, $printInfo);

        return $data;
    }

    /**
     * 打印组装工单
     */
    public function printTask_get(){
        $taskId = $this->get('taskId');
        $operator = $this->get('operator');
        $query1 = $this->task->getContentProduct($taskId);
        $query2 = $this->task->getContentDetail($taskId);
        $query3 = $this->task->getTaskBasicDetail($taskId);
        $id        = $query3['id'];
        $status    = $query3['status'];
        $boxSku    = $query3['sku'];
        $boxName   = $query3['name'];
        $createdAt = $query3['created_at'];
        $creater   = $query3['creater'];
        $boxQty    = $query3['qty'];

        $table = 'task_master';
        if ($status == 0){
            $data = array(
                'status' => 1
            );
            $cond = array('id' => $taskId);
            $this->utility->update($table, $data, $cond);
        }else if($status == 2){
            $data = array(
                'status' => 3
            );
            $cond = array('id' => $taskId);
            $this->utility->update($table, $data, $cond);
        }

        $this->_processProductExport($query1,$query2,$query3);
        $data = array(
            'task_print_time'     => date("YmdHis",time()),
            'task_print_operator' => $operator,
        );
        $cond = array('id' => $taskId);
        $this->utility->update($table, $data, $cond);

    }

    /**
     * 处理组装工单
     * @param $query1
     * @param $query2
     * @param $query3
     */
    public function _processProductExport($query1,$query2,$query3){
        $id        = $query3['id'];
        $boxSku    = $query3['sku'];
        $boxName   = $query3['name'];
        $createdAt = $query3['created_at'];
        $creater   = $query3['creater'];
        $boxQty    = $query3['qty'];

        // Starting the PHPExcel library
        $this->load->library('PHPExcel');
        $this->load->library('PHPExcel/IOFactory');
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->getProperties()->setTitle("export")->setDescription("none");
        // 设置title为box barcode,设置列名
        $currentSheet=$objPHPExcel->setactivesheetindex(0);
        $currentSheet=$objPHPExcel->getActiveSheet()->setTitle('box barcode');
        $currentSheet->getColumnDimension('a')->setWidth(20);
        $currentSheet->getColumnDimension('c')->setWidth(18);
        $currentSheet->getColumnDimension('d')->setWidth(24);
        //表名
        $currentSheet->getCell('a1')->setValue("礼盒加工单");
        $objPHPExcel->getActiveSheet()->mergeCells('a1:f1');
        $objPHPExcel->getActiveSheet()->getStyle('a1')->getAlignment()
            ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical();
        $objPHPExcel->getActiveSheet()->getStyle("a1")->getFont()->setSize(36);

        //库内作业任务基本数据
        $currentSheet->getCell('a3')->setValue("加工单号");
        $currentSheet->getCell('b3')->setValue("礼盒SKU");
        $currentSheet->getCell('c3')->setValue("礼盒名称");
        $currentSheet->getCell('d3')->setValue("创建日期");
        $currentSheet->getCell('e3')->setValue("创建人");
        $currentSheet->getCell('f3')->setValue("加工数量");

        $currentSheet->getCell('a4')->setValue("$id");
        $currentSheet->setCellValueExplicit('b4',$boxSku,PHPExcel_Cell_DataType::TYPE_STRING);
        $currentSheet->getColumnDimension('b')->setAutoSize(true);
        $currentSheet->getCell('c4')->setValue("$boxName");
        $currentSheet->getCell('d4')->setValue("$createdAt");
        $currentSheet->getCell('e4')->setValue("$creater");
        $currentSheet->getCell('f4')->setValue("$boxQty");
        $locationFrom = 'a3';
        $locationTo   = 'f4';
        $this->printBorder($objPHPExcel,$locationFrom,$locationTo);

        //拣货清单数据
        $currentSheet->getCell('a6')->setValue("拣货清单");
        $currentSheet->getCell('a7')->setValue("商品SKU");
        $currentSheet->getCell('b7')->setValue("商品条形码");
        $currentSheet->getCell('d7')->setValue("商品名称");
        $currentSheet->getCell('f7')->setValue("拣货数量");
        $objPHPExcel->getActiveSheet()->mergeCells('b7:c7');
        $objPHPExcel->getActiveSheet()->mergeCells('d7:e7');
        $objPHPExcel->getActiveSheet()->getStyle('b7')->getAlignment()
            ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical();
        $objPHPExcel->getActiveSheet()->getStyle('d7')->getAlignment()
            ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical();

        $aNet=0;
        foreach($query1 as $k => $value)
        {
            $sku     = $value['sku'];
            $barCode = $value['mbarcode'];
            $name    = $value['name'];
            $qty     = $value['qty'];

            $u1_net=$aNet+8;

            $currentSheet->setCellValueExplicit('a'.$u1_net,$sku,PHPExcel_Cell_DataType::TYPE_STRING);
            $currentSheet->setCellValueExplicit('b'.$u1_net,$barCode,PHPExcel_Cell_DataType::TYPE_STRING);
            $currentSheet->getCell('d'.$u1_net)->setValue("$name");
            $currentSheet->getCell('f'.$u1_net)->setValue("$qty");
            $objPHPExcel->getActiveSheet()->mergeCells("b$u1_net:c$u1_net");
            $objPHPExcel->getActiveSheet()->mergeCells("d$u1_net:e$u1_net");
            $objPHPExcel->getActiveSheet()->getStyle("b$u1_net")->getAlignment()
                ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical();
            $objPHPExcel->getActiveSheet()->getStyle("d$u1_net")->getAlignment()
                ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical();
            $aNet++;
        }
        $locationFrom = 'a7';
        $locationTo   = 'f'.$u1_net;
        $this->printBorder($objPHPExcel,$locationFrom,$locationTo);

        $bNet=0;
        $bNet=$u1_net+2;
        $cNet=$bNet+1;
        $dNet = 0;

        $currentSheet->getCell('a'.$bNet)->setValue("组合清单");
        $currentSheet->getCell('a'.$cNet)->setValue("组合代码");
        $currentSheet->getCell('b'.$cNet)->setValue("组装盒数");
        $currentSheet->getCell('c'.$cNet)->setValue("商品sku");
        $currentSheet->getCell('d'.$cNet)->setValue("商品条形码");
        $currentSheet->getCell('e'.$cNet)->setValue("商品名称");
        $currentSheet->getCell('f'.$cNet)->setValue("数量");
        $locationFrom = 'a'.$cNet;

        $prevBarCode = '';
        foreach($query2 as $k2 => $value2)
        {
            $barCodeBox = $value2['barcode'];
            $qtyBox     = $value2['qty'];
            $sku        = $value2['sku'];
            $barCode    = $value2['mbarcode'];
            $name       = $value2['name'];
            $u2_net=$cNet+1;
            if ($barCodeBox == $prevBarCode){
                $currentSheet->setCellValueExplicit('a'.$u2_net,$barCodeBox,PHPExcel_Cell_DataType::TYPE_STRING);
                $currentSheet->getCell('b'.$u2_net)->setValue("$qtyBox");
                $currentSheet->getColumnDimension('b')->setAutoSize(true);
                $currentSheet->setCellValueExplicit('c'.$u2_net,$sku,PHPExcel_Cell_DataType::TYPE_STRING);
                $currentSheet->setCellValueExplicit('d'.$u2_net,$barCode,PHPExcel_Cell_DataType::TYPE_STRING);
                $currentSheet->getColumnDimension('d')->setAutoSize(true);
                $currentSheet->getCell('e'.$u2_net)->setValue("$name");
                $currentSheet->getColumnDimension('e')->setAutoSize(true);
                $currentSheet->getCell('f'.$u2_net)->setValue("$qtyBox");
                $objPHPExcel->getActiveSheet()->mergeCells("a$dNet:a$u2_net");
                $objPHPExcel->getActiveSheet()->mergeCells("b$dNet:b$u2_net");
            }else{
                $prevBarCode = $barCodeBox;
                $currentSheet->setCellValueExplicit('a'.$u2_net,$barCodeBox,PHPExcel_Cell_DataType::TYPE_STRING);
                $currentSheet->getCell('b'.$u2_net)->setValue("$qtyBox");
                $currentSheet->getColumnDimension('b')->setAutoSize(true);
                $currentSheet->setCellValueExplicit('c'.$u2_net,$sku,PHPExcel_Cell_DataType::TYPE_STRING);
                $currentSheet->setCellValueExplicit('d'.$u2_net,$barCode,PHPExcel_Cell_DataType::TYPE_STRING);
                $currentSheet->getColumnDimension('d')->setAutoSize(true);
                $currentSheet->getCell('e'.$u2_net)->setValue("$name");
                $currentSheet->getColumnDimension('e')->setAutoSize(true);
                $currentSheet->getCell('f'.$u2_net)->setValue("$qtyBox");
                $dNet = $u2_net;
            }
            $cNet++;
        }

        $locationTo   = 'f'.$u2_net;
        $this->printBorder($objPHPExcel,$locationFrom,$locationTo);

        $objPHPExcel->setActiveSheetIndex(0);
        $objWriter = IOFactory::createWriter($objPHPExcel, 'Excel5');
        //发送标题强制用户下载文件
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="Task_'.date('dMy').'.xls"');
        header('Cache-Control: max-age=0');
        $objWriter->save('php://output');
    }


    /**
     * 打印条码
     * @param $objPHPExcel
     * @param $locationFrom
     * @param $locationTo
     */
    public function printBorder($objPHPExcel,$locationFrom,$locationTo){
        $styleArray = array(
            'borders' => array(
                'allborders' => array(
                    //'style' => PHPExcel_Style_Border::BORDER_THICK,//边框是粗的
                    'style' => PHPExcel_Style_Border::BORDER_THIN,//细边框
                    //'color' => array('argb' => 'FFFF0000'),
                ),
            ),
        );
        $objPHPExcel->getActiveSheet()->getStyle($locationFrom.':'.$locationTo)->applyFromArray($styleArray);
    }

    /**
     * 打印组装任务中所有礼盒BarCode
     */
    public function printBoxCode_get(){
        require_once(APPPATH.'third_party/libraries/tcpdf/tcpdf.php');
        require_once(APPPATH.'third_party/libraries/fpdi/fpdi.php');

        $pdf = new FPDI('p');
        $template = APPPATH.'third_party/template/blank_template.pdf';
        $font = new TCPDF_FONTS();
        $font1 = $font->addTTFfont(APPPATH.'third_party/libraries/tcpdf/fonts/Arial.ttf');

        $pdf->setSourceFile($template);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont($font1, '', 11);

        $operator = $this->get('operator');
        $taskId   = $this->get('taskId');
//        $result   = $this->task->getContentDetail($taskId);
        // 获取组装任务BarCode 和组装个数
        $result   = $this->task->getBarCodeDetail($taskId);
        // 获取组装任务状态
        $query3 = $this->task->getTaskBasicDetail($taskId);
        $status    = $query3['status'];

        $table = 'task_master';
        if ($status == 0){
            $data = array(
                'status' => 2
            );
            $cond = array('id' => $taskId);
            $this->utility->update($table, $data, $cond);
        }else if($status == 1){
            $data = array(
                'status' => 3
            );
            $cond = array('id' => $taskId);
            $this->utility->update($table, $data, $cond);
        }

        $pxCount=0;
        $pyCount=0;
        $ax = 3.9;
        foreach ($result as $k=>$v){
            for($i = 1; $i <= $v['qty']; $i++){
                $pxCount++;
                if($pxCount == 3){
                    $px = 67;
                    $ax = 68;
                    $py = 11 + $pyCount * 18;
                    $ay = 1.5 + $pyCount * 18;
                    $pxCount = 0;
                    $pyCount++;
                }elseif ($pxCount == 2){
                    $px = 35;
                    $ax = 36;
                    $py = 11 + $pyCount *18;
                    $ay = 1.5 + $pyCount * 18;
                }elseif ($pxCount == 1){
                    $px = 3;
                    $ax = 3.9;
                    $py = 11 + $pyCount * 18;
                    $ay = 1.5 + $pyCount * 18;
                }

                $pdf->write1DBarcode($v['barcode'], 'EAN13', $ax, $ay, 27);
                $pdf->SetXY($px, $py);
                $pdf->Cell(16, 3.5, $v['barcode'],'C');

                if ($pyCount == 8){
                    $pdf->AddPage();
                    $pyCount = 0;
                }
            }

        }
        $data = array(
            'barcocde_print_time'     => date("YmdHis",time()),
            'barcocde_print_operator' => $operator,
        );
        $cond = array('id' => $taskId);
        $this->utility->update($table, $data, $cond);
        $pdf->Output("Task".$taskId.date("YmdHis",time()).".pdf", "I");
    }

    /**
     * 组装任务新增接口
     */
    public function add_post()
    {
        $code = 1;
        $message = '组装任务创建成功';
        $data = array();
        header("Access-Control-Allow-Origin: *");
        $boxSku = $this->post('boxSku');
        $qty = $this->post('qty');
        $prodType = $this->post('prodType');
        $comment= $this->post('comment');
        $operator = $this->post('operator');
        $content = $this->post('content');
        $content = json_decode($content,true);

        // 验证参数
        if(!$this->_checkTaskParam($boxSku,$qty,$prodType,$operator,$content))
        {
            return;
        }

        $boxId = $this->master->getBoxId($boxSku);

        // 处理
        if(!$this->_processAdd($boxId,$qty,$prodType,$operator,$comment,$content))
        {
            return;
        }

        $out = array(
            'code'  =>  $code,
            'msg'   =>  $message,
            'data'  =>  $data,
        );
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    /**
     * 验证组装任务创建参数
     * @param $boxSku
     * @param $qty
     * @param $prodType
     * @param $operator
     * @param $content
     * @return bool
     */
    protected function _checkTaskParam($boxSku,$qty,$prodType,$operator,$content)
    {
        // 礼盒id验证
        if(is_null($boxSku))
        {
            $this->returnError('礼盒sku不能为空');
            return false;
        }
        else if(!$this->utility->select_row('box_master','sku',array('sku' => $boxSku)))
        {
            $this->returnError('该礼盒sku不存在,无法创建组装任务');
            return false;
        }

        // 组装数量验证
        if($qty <= 0)
        {
            $this->returnError("组装数量必须为正数");
            return false;
        }

        // 参数录入方式验证
        if(($prodType != 'B') && ($prodType != 'S'))
        {
            $this->returnError("录入方式不合法或者为空");
            return false;
        }

        // 参数创建人验证
        if(is_null($operator))
        {
            $this->returnError('创建人不能为空');
            return false;
        }

        // 参数组装项验证
        if(is_null($content))
        {
            $this->returnError('组装任务组装项不能为空');
            return false;
        }
        return true;
    }


    /**
     * 处理组装任务的创建
     * @param $boxId
     * @param $qty
     * @param $prodType
     * @param $operator
     * @param $comment
     * @param $content
     * @return bool
     */
    protected function _processAdd($boxId,$qty,$prodType,$operator,$comment,$content)
    {
        // 事务处理
        $this->db->trans_start();

        // 创建组装任务主数据
        try
        {
            $taskId = $this->_createTaskMaster($boxId,$qty,$comment,$operator);
        }
        catch(Exception $e)
        {
            $this->returnError('组装任务主数据创建失败。'.$e->getMessage());
            return false;
        }

        // 创建组装任务组装项数据
        try
        {
            $this->_createTaskItem($taskId,$boxId,$content,$prodType);
        }
        catch(Exception $e)
        {
            $this->returnError('组装任务组装项数据创建失败。'.$e->getMessage());
            return false;
        }

        // 创建组装任务组合数据
        try
        {
            $this->_createTaskPattern($taskId,$boxId);
        }
        catch(Exception $e)
        {
            $this->returnError('组装任务组合数据创建失败。'.$e->getMessage());
            return false;
        }

        $this->db->trans_complete();
        return true;
    }


    /**
     * 创建组装任务主数据
     * @param $boxId
     * @param $qty
     * @param $comment
     * @param $creater
     * @return mixed
     */
    protected function _createTaskMaster($boxId,$qty,$comment,$creater)
    {
        $updateData = array(
            'del_flg'   =>  0,
        );
        $this->utility->update('box_master',$updateData,array('id' => $boxId));

        $taskId = $this->task->generateTaskId();
        $taskMasterData = array(
            'id'            =>  $taskId,
            'box_id'        =>  $boxId,
            'qty'           =>  $qty,
            'comment'       =>  $comment,
            'creater'       =>  $creater,
            'created_at'    =>  date('Y-m-d H:i:s',time()),
        );
        $this->utility->insert('task_master',$taskMasterData);
        return $taskId;
    }

    /**
     * 创建组装任务组装项数据
     * @param $taskId
     * @param $boxId
     * @param $content
     * @param $prodType
     * @return bool
     * @throws Exception
     */
    protected function _createTaskItem($taskId,$boxId,$content,$prodType)
    {
        foreach($content as $ik=>$iv)
        {
            foreach($iv as $k=>$v)
            {
                switch($prodType)
                {
                    case 'B':
                        $keyName = 'barcode';
                        break;
                    case 'S':
                        $keyName = 'sku';
                        break;
                    default:
                        break;
                }
                $productId = $this->utility->getProductId($v[$keyName],$prodType);
                $boxItemId = $this->task->getBoxItemId($ik,$boxId,$productId);
                // 组装项不存在
                if(!$boxItemId)
                {
                    throw new Exception('系统中不存在组装项'.$v[$keyName]);
                    return false;
                }
                else{
                    $this->task->addTaskItem($taskId,$boxItemId,$v['qty']);
                }
            }
        }
        return true;
    }

    /**
     * 创建组装任务组合数据
     * @param $taskId
     * @param $boxId
     * @return bool
     */
    protected function _createTaskPattern($taskId,$boxId)
    {
        $result = true;
        $taskItemInfo = $this->task->getTaskItemInfo($taskId);
        // 获取组装项总个数
        $itemSql = 'select distinct(item_id) from box_item where box_id = '.$boxId;
        $boxItemInfo = $this->db->query($itemSql)->result();
        $itemNum = count($boxItemInfo);
        $this->_calTaskPattern($taskItemInfo,$itemNum,$taskId,$boxId);
        return $result;
    }



    /**
     * 初始化算法辅助表
     */
    public function initiate_get()
    {
        $this->db->simple_query('truncate table pattern_helper;');
        for($i = 0; $i< 10000;$i++)
        {
            $this->utility->insert('pattern_helper',array('empty' => ''));
        }
    }

    /**
     * 计算组装任务的组装方式
     * @param $taskItemInfo
     * @param $itemNum
     * @param $taskId
     * @param $boxId
     * @return array
     */
    /*
     * 计算组合的语句例子
     * select a0.item_id as item_id0,a0.pid as pid0,a1.item_id as item_id1,a1.pid as pid1,a2.item_id as item_id2,a2.pid as pid2,count(*) as qty
                from (
                select item_id, pid, @rownum0 := @rownum0+1 row_id
                from (
                        select "1" as item_id, "1" as pid, RAND(now()) rand_num from (select 1 from pattern_helper limit 1,50) a

                        union
                        select "1" as item_id, "7" as pid, RAND(now()) rand_num from (select 1 from pattern_helper limit 1,50) a

                ) a, (select @rownum0 := 0) b
                #order by rand_num  #如果要随机就加上这行
            ) a0
                left join (
                select item_id, pid, @rownum1 := @rownum1+1 row_id
                from (
                        select "2" as item_id, "2" as pid, RAND(now()) rand_num from (select 1 from pattern_helper limit 1,100) a

                ) a, (select @rownum1 := 0) b
                #order by rand_num  #如果要随机就加上这行
            ) a1 on a0.row_id=a1.row_id
                left join (
                select item_id, pid, @rownum2 := @rownum2+1 row_id
                from (
                        select "3" as item_id, "8" as pid, RAND(now()) rand_num from (select 1 from pattern_helper limit 1,60) a

                        union
                        select "3" as item_id, "4" as pid, RAND(now()) rand_num from (select 1 from pattern_helper limit 1,40) a

                ) a, (select @rownum2 := 0) b
                #order by rand_num  #如果要随机就加上这行
            ) a2 on a0.row_id=a2.row_id
        group by a0.pid,a1.pid,a2.pid
        order by a0.pid,a1.pid,a2.pid
     */
    protected function _calTaskPattern($taskItemInfo,$itemNum,$taskId,$boxId)
    {
        $result = array();
        $i = 0;
        $sql = 'select ';
        while($i < $itemNum)
        {
            $sql .= 'a' . $i . '.item_id as item_id'.$i.',a'. $i .'.pid as pid'.$i.',';
            $i++;
        }
        $sql .= 'count(*) as qty ';

        $j = 0;
        while($j < $itemNum) {
            if($j > 0)
            {
                $sql .= '
                left join (
                ';
            }
            else
            {
                $sql .= '
                from (
                ';
            }

            $sql .= 'select item_id, pid, @rownum'. $j .' := @rownum'. $j .'+1 row_id
                from (
                        ';

            $unionCnt = 0;
            $unionStr = '';
            foreach ($taskItemInfo as $item) {
                if($item->item_id == ($j+1))
                {
                    switch($unionCnt)
                    {
                        case 0:
                            $unionStr = '';
                            break;
                        case 1:
                            $unionStr = '
                        union
                        ';
                            break;
                        default:
                            $unionStr = '
                        union all
                        ';
                            break;
                    }
                    $unionCnt ++;
                    $sql .= $unionStr;
                    $sql .= 'select "'.$item->item_id.'" as item_id, "'.$item->product_id.'" as pid,';
                    $sql .= ' RAND(now()) rand_num from (select 1 from pattern_helper limit 1,';
                    $sql .= $item->qty . ') a
                ';
                }

            }

            $sql .= '
                ) a, (select @rownum'. $j .' := 0) b
            ) a'.$j;
            if($j > 0)
            {
                $sql .= ' on a0.row_id=a'. $j . '.row_id';
            }
            $j++;
        }
        $listStr = '';
        $k = 0;
        while($k < $itemNum)
        {
            if($k > 0)
            {
                $listStr .= ",";
            }
            $listStr .= 'a' . $k . '.pid';
            $k++;
        }
        $sql .= '
        group by '. $listStr;
        $sql .= '
        order by '. $listStr;



        $res = $this->_insertTaskPattern($sql,$itemNum,$taskId,$boxId);
        return $res;
    }

    /**
     * 创建组装任务组装项
     * @param $sql
     * @param $itemNum
     * @param $taskId
     * @param $boxId
     * @return bool
     * @throws Exception
     */
    protected function _insertTaskPattern($sql,$itemNum,$taskId,$boxId)
    {
        $query = $this->db->query($sql);
        // std Object转array
        $arrRes =  json_decode( json_encode( $query->result()),true);

        foreach($arrRes as $row)
        {
            $qty = $row['qty'];
            $barCode = '';
            $barcodeSql = 'select bp0.barcode from box_pattern bp0
            ';
            $i= 1;
            while ($i < $itemNum)
            {
                $barcodeSql .= 'left join box_pattern bp'.$i.' on bp'.$i.'.barcode = bp0.barcode
            ';
                $i ++;
            }

            $j = 0;
            while ($j < $itemNum)
            {
                if($j == 0)
                {
                    $barcodeSql .= 'where ';
                }
                else
                {
                    $barcodeSql .= ' and ';
                }
                $barcodeSql .= 'bp'.$j.'.item_id = '.$row['item_id'.$j].' and bp'.$j.'.product_id = '.$row['pid'.$j].'
            ';
                $j ++;
            }

            $barcodeSql .= 'and bp0.box_id = '.$boxId;
            $barcodeQuery = $this->db->query($barcodeSql);
            foreach($barcodeQuery->result() as $barRow) {
                $barCode = $barRow->barcode;
            }
            if(!$barCode)
            {
                throw new Exception($barcodeQuery);
                return false;
            }
            //插入组装任务组合数据
            $taskPatternData = array(
                'task_id'       =>  $taskId,
                'barcode'       =>  $barCode,
                'qty'           =>  $qty,
            );

            $this->utility->insert('task_pattern',$taskPatternData);
        }

    }

    /**
     * 组装任务删除接口
     */
    public function delete_post()
    {
        $code = 1;
        $message = '组装任务删除成功';
        $data = array();
        header("Access-Control-Allow-Origin: *");
        $taskId = $this->post('taskId');
        $operator = $this->post('operator');

        // 验证参数
        if(!$this->_checkDeleteParam($taskId,$operator))
        {
            return;
        }

        // 处理
        if(!$this->_processTaskDelete($taskId))
        {
            return;
        }

        $out = array(
            'code'  =>  $code,
            'msg'   =>  $message,
            'data'  =>  $data,
        );
        //$out = json_encode($out);
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    /**
     * 验证组装任务删除接口参数
     * @param $taskId
     * @param $operator
     * @return bool
     */
    protected function _checkDeleteParam($taskId,$operator)
    {
        // 参数taskId验证
        if(is_null($taskId))
        {
            $this->returnError('组装任务id不能为空');
            return false;
        }

        $taskInfo = $this->utility->select_row('task_master','status',array('id' => $taskId));
        if(!$taskInfo)
        {
            $this->returnError('该组装任务不存在。');
            return false;
        }
        else if($taskInfo->status == self::STATUS_COMPLETE)
        {
            $this->returnError('该组装任务已完成,无法删除。');
            return false;
        }

        // 参数操作人员验证
        if(is_null($operator))
        {
            $this->returnError('操作人员不能为空');
            return false;
        }

        return true;
    }

    /**
     * 处理组装任务删除
     * @param $taskId
     * @return bool
     */
    protected function _processTaskDelete($taskId)
    {

        $delCond = array(
            "task_id" => $taskId,
        );

        // 事务处理
        $this->db->trans_start();

        // 重置礼盒删除标记位
        try
        {
            $this->_updateBoxDelFlg($taskId);
        }
        catch(Exception $e)
        {
            $this->returnError('标记位重置失败。'.$e->getMessage());
            return false;
        }
        // 删除组装任务主数据
        try
        {
            $this->utility->delete('task_master',array('id' => $taskId));
        }
        catch(Exception $e)
        {
            $this->returnError('组装任务主数据删除失败。'.$e->getMessage());
            return false;
        }

        // 删除组装任务组装项数据
        try
        {
            $this->utility->delete('task_item',$delCond);
        }
        catch(Exception $e)
        {
            $this->returnError('组装任务组装项数据删除失败。'.$e->getMessage());
            return false;
        }

        // 删除组装任务组合数据
        try
        {
            $this->utility->delete('task_pattern',$delCond);
        }
        catch(Exception $e)
        {
            $this->returnError('组装任务组合数据删除失败。'.$e->getMessage());
            return false;
        }

        $this->db->trans_complete();
        return true;
    }

    /**
     * 重置礼盒删除标记位
     * @param $taskId
     * @return bool
     */
    protected function _updateBoxDelFlg($taskId)
    {
        $boxIdInfo = $this->utility->select_row('task_master','box_id',array('id' => $taskId));
        if($boxIdInfo)
        {

            if(!$this->utility->select_count('task_master','box_id ='.$boxIdInfo->box_id.' and id <> "'.$taskId.'"'))
            {
                $update_data = array(
                    'del_flg' => 1,
                );
                $this->utility->update('box_master',$update_data,array('id' => $boxIdInfo->box_id));
            }

        }
        return true;
    }

    /**
     * 完成组装任务接口
     */
    public function complete_post()
    {
        $code = 1;
        $message = '组装任务完成成功';
        $data = array();
        header("Access-Control-Allow-Origin: *");
        $taskId = $this->post('taskId');
        $operator = $this->post('operator');

        // 验证参数
        if(!$this->_checkCompleteParam($taskId,$operator))
        {
            return;
        }

        // 处理
        if(!$this->_processComplete($taskId,$operator))
        {
            return;
        }

        $out = array(
            'code'  =>  $code,
            'msg'   =>  $message,
            'data'  =>  $data,
        );
        //$out = json_encode($out);
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code

    }


    /**
     * 验证组装任务完成接口参数
     * @param $taskId
     * @param $operator
     * @return bool
     */
    protected function _checkCompleteParam($taskId,$operator)
    {
        // 参数taskId验证
        if(is_null($taskId))
        {
            $this->returnError('组装任务id不能为空');
            return false;
        }

        $taskInfo = $this->utility->select_row('task_master','status',array('id' => $taskId));
        if(!$taskInfo)
        {
            $this->returnError('该组装任务不存在。');
            return false;
        }
        else if($taskInfo->status != self::STATUS_ALL_PRINT)
        {
            $this->returnError('组装任务必须全部打印才能完成。');
            return false;
        }

        // 参数操作人员验证
        if(is_null($operator))
        {
            $this->returnError('操作人员不能为空');
            return false;
        }

        return true;
    }

    /**
     * 处理组装任务完成
     * @param $taskId
     * @param $operator
     * @return bool
     */
    protected function _processComplete($taskId,$operator)
    {
        // 事务处理
        $this->db->trans_start();

        // 更新组装任务状态
        try
        {
            $this->_completeTaskStatus($taskId);
        }
        catch(Exception $e)
        {
            $this->returnError('组装任务状态更新失败。'.$e->getMessage());
            return false;
        }

        // 更新组装任务库存信息
        try
        {
            $this->_updateTaskInventory($taskId,$operator);
        }
        catch(Exception $e)
        {
            $this->returnError('组装任务组装项数据删除失败。'.$e->getMessage());
            return false;
        }

        $this->db->trans_complete();
        return true;
    }


    /**
     * 更新完成组装任务的状态
     * @param $taskId
     */
    private function _completeTaskStatus($taskId)
    {
        $updateData = array(
            'status'    =>  self::STATUS_COMPLETE,
        );
        $updateCond = array(
            'id'        =>  $taskId,
        );
        $this->utility->update('task_master', $updateData,$updateCond);
    }

    /**
     * 更新组装任务库存信息
     * @param $taskId
     * @param $operator
     */
    protected function _updateTaskInventory($taskId,$operator)
    {
        $taskPattern = $this->task->getTaskPattern($taskId);

        foreach($taskPattern as $row)
        {
            $this->task->addInventory($row->barcode,$row->qty,$operator);
        }
    }

    /**
     * 礼盒入库接口
     */
    public function instock_post()
    {
        $code = 1;
        $message = '礼盒入库成功';
        $data = array();
        header("Access-Control-Allow-Origin: *");
        $barcode = $this->post('barCode');
        $qty = $this->post('qty');
        $operator = $this->post('operator');

        // 验证参数
        if(!$this->_checkMovementParam($barcode,$qty,$operator,MOVEMENT_TYOE_IN))
        {
            return;
        }

        // 处理
        if(!$this->_processInstock($barcode,$qty,$operator))
        {
            return false;
        }

        $out = array(
            'code'  =>  $code,
            'msg'   =>  $message,
            'data'  =>  $data,
        );
        //$out = json_encode($out);
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }


    /**
     * 处理入库
     * @param $barcode
     * @param $qty
     * @param $operator
     * @return bool
     */
    protected function _processInstock($barcode,$qty,$operator)
    {
        // 入库操作
        try
        {
            $this->task->addInventory($barcode,$qty,$operator);
        }
        catch(Exception $e)
        {
            $this->returnError('入库操作失败。'.$e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * 礼盒出库接口
     */
    public function outstock_post()
    {
        $code = 1;
        $message = '礼盒入库成功';
        $data = array();
        header("Access-Control-Allow-Origin: *");
        $barcode = $this->post('barCode');
        $qty = $this->post('qty');
        $operator = $this->post('operator');

        // 验证参数
        if(!$this->_checkMovementParam($barcode,$qty,$operator,MOVEMENT_TYOE_OUT))
        {
            return;
        }

        // 处理
        if(!$this->_processOutstock($barcode,$qty,$operator))
        {
            return;
        }


        $out = array(
            'code'  =>  $code,
            'msg'   =>  $message,
            'data'  =>  $data,
        );
        //$out = json_encode($out);
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    /**
     * 处理出库
     * @param $barcode
     * @param $qty
     * @param $operator
     * @return bool
     */
    protected function _processOutstock($barcode,$qty,$operator)
    {
        // 出库操作
        try
        {
            $this->task->removeInventory($barcode,$qty,$operator);
        }
        catch(Exception $e)
        {
            $this->returnError('出库操作失败。'.$e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * 验证出入库接口参数
     * @param $barcode
     * @param $qty
     * @param $operator
     * @param $type
     * @return bool
     */
    protected function _checkMovementParam($barcode,$qty,$operator,$type)
    {
        // 参数barcode验证
        if(is_null($barcode))
        {
            $this->returnError('条形码不能为空');
            return false;
        }
        else if(!$this->utility->select_row('task_pattern','id',array('barcode' => $barcode)))
        {
            $this->returnError('条形码在系统中不存在');
            return false;
        }

        // 参数qty验证
        if(is_null($qty))
        {
            $this->returnError('数量不能为空');
            return false;
        }
        else if ($qty <= 0)
        {
            $this->returnError('数量必须为正数');
            return false;
        }

        // 参数操作人员验证
        if(is_null($operator))
        {
            $this->returnError('操作人员不能为空');
            return false;
        }

        if($type == MOVEMENT_TYOE_OUT)
        {
            // 库存验证
            $inventory = $this->task->getInventoryQty($barcode);
            $left = $inventory['qty'] - $qty;
            if($left < 0){
                $this->returnError('库存不足,无法完成出库');
                return false;
            }
        }

        return true;
    }

    /**
     * 礼盒列表接口
     */
    public function boxlist_get()
    {
        $code = 1;
        $message = '礼盒列表获取成功';
        $data = array();
        header("Access-Control-Allow-Origin: *");
        $operator = $this->get('operator');

        // 验证参数
        if(!$this->_checkBoxListParam($operator))
        {
            return;
        }

        // 处理
        $data = $this->_processBoxList();

        $out = array(
            'code'  =>  $code,
            'msg'   =>  $message,
            'data'  =>  json_encode($data),
        );
        $this->set_response($out, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    /**
     * 验证礼盒列表接口参数
     * @param $operator
     * @return bool
     */
    protected function _checkBoxListParam($operator)
    {
        // 参数操作人员验证
        if(is_null($operator))
        {
            $this->returnError('操作人员不能为空');
            return false;
        }

        return true;
    }

    /**
     * 处理礼盒列表
     * @return array
     */
    protected function _processBoxList()
    {
        $result = array();
        $this->db->select('sku,name');
        $query = $this->db->get('box_master');
        foreach($query->result() as $row)
        {
            $boxInfo = array(
                'sku'   =>  $row->sku,
                'name'  =>  $row->name,
            );
            $result[] = $boxInfo;
        }
        return $result;
    }

}
