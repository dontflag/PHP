<?
$params = "lbd";
include "sintar2007.php";

$configdb = ibase_connect($config);
$cont = substr(basename($lbd,'.ib'),3); //имя контроллера
$NetAddress = Array (); //NETADDRESS->ADRVALUE WHERE NODEID=..
$PinMK = Array ();
$MKs = Array ();
$UNOs = Array ();

class RecUNO
{
    public $exch_type = '';
    public $num_lan = '';
    public function __construct($NetName) {
        $this->num_lan = GetNetNo($NetName);
        $this->exch_type = GetExchType($NetName);
    }
}

class RecMK
{
    public $ab_uno = '';
    public $device = '';
    public $exch_type = 'RW';
    public $MKName = '';
    public $num_rs = '';
    public $takt = '';
    public function __construct($MK,$NumRS,$Net) {  //$Net - исследуемая сеть контроллера
        $this->MKName = $MK;
        $this->device = GetDeviceType($MK);
        $this->num_rs = $NumRS;
        $this->takt = GetTakt($MK);
    }
}

$qry = "SELECT NODEID FROM ASTREE WHERE NAME = '{$cont}' AND CATEGORY = 3";
$recs = ibase_query($configdb,$qry);
while ($rec = ibase_fetch_object($recs)) {
    $ContID = $rec->NODEID;  //ID контроллера
}
$qry = "SELECT NETID FROM NETADDRESS WHERE NODEID = '{$ContID}'";
$recs = ibase_query($configdb,$qry);
while ($rec = ibase_fetch_object($recs)) {
    $NetID = $rec->NETID;   //ID сети, обрабатываемоей в текущем такте цикла
    $qry = "SELECT NAME FROM NET WHERE NETID = '{$NetID}'";
    $InsRecs = ibase_query($configdb,$qry);
    while ($InsRec = ibase_fetch_object($InsRecs)) {
        $NetName = $InsRec->NAME;   //имя этой сети
    }
    $NetType = str_split($NetName,2);
    if ($NetType[0] == 'rs') {
        $qry = "SELECT ADRVALUE FROM NETADDRESS WHERE NETID = '{$NetID}' AND NODEID = '{$ContID}'";
        $InsRecs = ibase_query($configdb,$qry);
        while ($InsRec = ibase_fetch_object($InsRecs)) {
            $NumRS = $InsRec -> ADRVALUE;
        }
        if ($NumRS == ' ') {
            echo "Не задан адрес в сети $NetName\n";
        }
        $qry = "SELECT NODEID,NAME FROM ASTREE WHERE PARENTID = '{$ContID}'";
        $InsRecs = ibase_query($configdb,$qry);
        while ($InsRec = ibase_fetch_object($InsRecs)){
            $PinMK['NODEID'] = $InsRec->NODEID;
            $PinMK['NAME'] = $InsRec->NAME;
            $MK = GetMK($PinMK);
            if (count($MK) > 0){
                $qry = "SELECT * FROM ISNETCONTROLLER('{$MK['NODEID']}')"; //вызов и вывод ХП
                $prep = ibase_prepare($qry);
                $rs = ibase_execute ($prep);
                $row = ibase_fetch_row($rs);
                $ProcRes = $row[0];
                if ($ProcRes == 0 ) {
                    $AbUno = GetAbUno($MK['NODEID'],$NetName);
                    if ($AbUno<>'') {
                         if (in_array($PinMK['NAME'],$MKs) == false) {
                            $MKRec = new RecMK($MK['NODEID'],$NumRS,$NetName);
                            $MKs[] = $MK['NAME'];
                            if ($AbUno == 'zero') {
                               $CfgLineRS = "NUM_RS<$MKRec->num_rs>DEVICE<$MKRec->device>AB_RS<0>TYPE<$MKRec->exch_type>TAKT_MK<$MKRec->takt>";
                            } else { 
                                $CfgLineRS = "NUM_RS<$MKRec->num_rs>DEVICE<$MKRec->device>AB_RS<$AbUno>TYPE<$MKRec->exch_type>TAKT_MK<$MKRec->takt>";
                            }
                            echo "$CfgLineRS\n";
                         }
                    }
                }
            }
        }
    } else if (in_array($NetName,$UNOs) == false) {
        $UNORec = new RecUNO($NetName);
        $UNOs[] = $NetName;
        $CfgLineUNO = "ETHER<$UNORec->num_lan>TYPE<$UNORec->exch_type>";
        echo "$CfgLineUNO\n";
    }
}

function GetNetNo($Net_Name) {
    $Res = '';
    $Name = array();
    $Nums = array('1','2','3','4','5','6','7','8','9','0');
    $Name = preg_split('//',$Net_Name);
    foreach ($Name as &$Value) {
        if (in_array($Value,$Nums)) {
            $Res = $Res.$Value;
        }
    }
    return $Res;
}

function GetExchType($NetName) {
    $Res = strstr($NetName,'VU');
    if ($Res == false) {
        $Res = 'UNO';
    } else {
        $Res = 'VU';
    }
    return $Res;
}

function GetNetID($NetName) {
    global $configdb;
    $qry = "SELECT NETID FROM NET WHERE NAME = '{$NetName}'";
    $recs = ibase_query($configdb,$qry);
    while ($rec = ibase_fetch_object($recs)) {
        $NetID = $rec->NETID;
    }
    return $NetID;
}

function GetAbUno($MK,$NetMK) { 
    global $configdb;
    global $cont;
    $Res = '';
    $Cat = '';
    $qry = "SELECT CATEGORY FROM ASTREE WHERE NODEID = '{$MK}'";
    $recs = ibase_query($configdb,$qry);
    while ($rec = ibase_fetch_object($recs)) {
        $Cat = $rec->CATEGORY;
    }
    if ($Cat == 3) {
        $NetID = GetNetID($NetMK);
        $qry = "SELECT ADRVALUE FROM NETADDRESS WHERE NODEID = '{$MK}' AND NETID = '{$NetID}'";
        $recs = ibase_query($configdb,$qry);
        while ($rec = ibase_fetch_object($recs)) {
            $Res = $rec->ADRVALUE;
        }
    } else {
        $qry = "SELECT ABNNO FROM ASTREE WHERE NODEID = '{$MK}'";
        $recs = ibase_query($configdb,$qry);
        while ($rec = ibase_fetch_object($recs)) {
            $Res = $rec->ABNNO;
        }
    }
    $IsUno = strstr($cont,'UNO');
    if ($IsUno == false) {
        $Res = 'zero';
    }
    return $Res;
}

function GetDeviceType ($MK) {
    global $configdb;
    $Res = '';
    $qry = "SELECT CATEGORY FROM ASTREE WHERE NODEID = '{$MK}'";
    $recs = ibase_query($configdb,$qry);
    while ($rec = ibase_fetch_object($recs)){
        $cat = $rec->CATEGORY;
    } 
    if ($cat == '3') {
        $qry = "SELECT * FROM ISNETCONTROLLER('{$MK}')"; //âûçîâ è âûâîä ÕÏ
        $prep = ibase_prepare($qry);
        $rs = ibase_execute ($prep);
        $row = ibase_fetch_row($rs);
        $ProcRes = $row[0];
        if ($ProcRes >0) {
            $Res = 'UNO';
        } else {
            $qry = "SELECT PARENTID FROM ASTREE WHERE NODEID = '{$MK}'";
            $recs = ibase_query($configdb,$qry);
            while ($rec = ibase_fetch_object($recs)) {
                $ParentID = $rec->PARENTID;
            }
            $qry = "SELECT NAME FROM ASTREE WHERE NODEID = '{$ParentID}'";
            $recs = ibase_query($configdb,$qry);
            while ($rec = ibase_fetch_object($recs)) {
                $ParentMKName = $rec->NAME;
            }
            $ParCheck = str_split($ParentMKName,2);
            if ($ParCheck[0] == 'WS') {
                $Res = 'RS';
            } else {
                $Res = 'MK';
            }
        }
    } else if ($cat = '4') {
        $Res = 'MK';
    }
    return $Res;
}

function GetTakt($MK) {
    global $configdb;
    $Res='';
    $qry = "SELECT SENSCOUNT FROM ASTREE WHERE NODEID ='{$MK}'"; //Категория 3 потому что в NETADDRESS->NODEID RS-сетей фигурирует только контроллеры
    $recs = ibase_query($configdb,$qry);
    while ($rec = ibase_fetch_object($recs)) {
        $Res = $rec->SENSCOUNT;
    }
    if ($Res == '') {
        $Res = '0';
    }
    return $Res;
}

function GetMK ($PinMK) {
    global $configdb;
    $MK = array();
    $MKChildID =' ';
    $qry = "SELECT FINNODEID FROM LINK WHERE STARTNODEID = '{$PinMK['NODEID']}'";
    $recs = ibase_query($configdb,$qry);
    if (empty($recs->FINNODEID)) { //SELECT получил пустоту
        $qry = "SELECT STARTNODEID FROM LINK WHERE FINNODEID = '{$PinMK['NODEID']}'";
        $InsRecs = ibase_query($configdb,$qry);
        while ($InsRec = ibase_fetch_object($InsRecs)) {
            $MKChildID = $InsRec->STARTNODEID;
        }
    }  
    if ($MKChildID == ' ') {
        while ($rec = ibase_fetch_object($recs)) {
            $MKChildID = $rec->FINNODEID;
        }
    }
    if ($MKChildID <>' ') {
        $qry = "SELECT PARENTID FROM ASTREE WHERE NODEID = '{$MKChildID}'";
        $recs = ibase_query($configdb,$qry);
        while ($rec = ibase_fetch_object($recs)) {
            $MK['NODEID'] = $rec -> PARENTID;
            
        }
        $qry = "SELECT NAME FROM ASTREE WHERE NODEID = '{$MK['NODEID']}'";
        $recs = ibase_query($configdb,$qry);
        while ($rec = ibase_fetch_object ($recs)) {
            $MK['NAME'] = $rec -> NAME;
        }
    }
    return $MK;
}

        
?>
