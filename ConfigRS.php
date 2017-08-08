<?
$params = "lbd";
include "sintar2007.php";

$configdb = ibase_connect($config);
$cont = substr(basename($lbd,'.ib'),3); //имя контроллера
$NetAddress = Array (); //NETADDRESS->ADRVALUE WHERE NODEID=..
$PinMK = Array ();
$MKs = Array ();
$UNOs = Array ();
$RecUNO = Array ("exch_type" => "","num_lan"=>"");
$RecMK = Array ("device"=>"","exch_type"=>"RW","MKName"=>"","num_rs"=>"","takt"=>"");

$qry = "SELECT NODEID, FLAGS FROM ASTREE WHERE NAME = '{$cont}' AND CATEGORY = 3";
$recs = ibase_query($configdb,$qry);
while ($rec = ibase_fetch_object($recs)) {
    $ContID = $rec->NODEID;  //ID контроллера
    $FPOCodeNo = ((int)$rec->FLAGS/2048)%16;    //Flags div 2048 mod 16;
}
$qry = "SELECT N.NAME, NA.NETID FROM NET N, NETADDRESS NA WHERE NA.NODEID = '{$ContID}' AND N.NETID = NA.NETID";
$recs = ibase_query($configdb,$qry);
while ($rec = ibase_fetch_object($recs)) {
    $NetID = $rec->NETID;   //ID сети, обрабатываемоей в текущем такте цикла
    $NetName = $rec->NAME;   //имя сети
    $NetType = str_split($NetName,2);
    if ($NetType[0] == 'rs') {
        $qry = "SELECT ADRVALUE FROM NETADDRESS WHERE NETID = '{$NetID}' AND NODEID = '{$ContID}'";
        $InsRecs = ibase_query($configdb,$qry);
        while ($InsRec = ibase_fetch_object($InsRecs)) 
            $NumRS = $InsRec -> ADRVALUE;
        if ($NumRS == ' ') 
            echo "Íå çàäàí àäðåñ â ñåòè  $NetName\n";
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
                            $RecMK["MKName"] = $MK['NAME'];
                            $RecMK["device"] = GetDeviceType($MK['NODEID']);
                            $RecMK["num_rs"] = $NumRS;
                            $RecMK["takt"] = GetTakt($MK['NODEID']);
                            $MKs[] = $MK['NAME'];
                            if ($AbUno == 'zero') 
                               $CfgLineRS = "NUM_RS<{$RecMK["num_rs"]}>DEVICE<{$RecMK['device']}>AB_RS<0>TYPE<{$RecMK['exch_type']}>TAKT_MK<{$RecMK['takt']}>";
                            else  
                                $CfgLineRS = "NUM_RS<{$RecMK["num_rs"]}>DEVICE<{$RecMK['device']}>AB_RS<$AbUno>TYPE<{$RecMK['exch_type']}>TAKT_MK<{$RecMK['takt']}>";
                            echo "$CfgLineRS\n";
                         }
                    }
                }
            }
        }
    } 
    else if (in_array($NetName,$UNOs) == false) {
        $RecUNO["num_lan"] = GetNetNo($NetName);
        $RecUNO["exch_type"] = GetExchType($NetName);
        $UNOs[] = $NetName;
        $CfgLineUNO = "ETHER<{$RecUNO["num_lan"]}>TYPE<{$RecUNO["exch_type"]}>";
        echo "$CfgLineUNO\n";
    }
}

function GetNetNo($Net_Name) {
    $Res = '';
    $Name = array();
    $Nums = array('1','2','3','4','5','6','7','8','9','0');
    $Name = preg_split('//',$Net_Name);
    foreach ($Name as &$Value) 
        if (in_array($Value,$Nums)) 
            $Res = $Res.$Value;
    return $Res;
}

function GetExchType($NetName) {
    $Res = strstr($NetName,'VU');
    if ($Res == false) 
        $Res = 'UNO';
    else 
        $Res = 'VU';
    return $Res;
}

function GetNetID($NetName) {
    global $configdb;
    $qry = "SELECT NETID FROM NET WHERE NAME = '{$NetName}'";
    $recs = ibase_query($configdb,$qry);
    while ($rec = ibase_fetch_object($recs)) 
        $NetID = $rec->NETID;
    return $NetID;
}

function GetAbUno($MK,$NetMK) { 
    global $configdb;
    global $cont;
    global $FPOCodeNo;
    $Res = '';
    $Cat = '';
    $qry = "SELECT CATEGORY FROM ASTREE WHERE NODEID = '{$MK}'";
    $recs = ibase_query($configdb,$qry);
    while ($rec = ibase_fetch_object($recs)) 
        $Cat = $rec->CATEGORY;
    if ($Cat == 3) {
        $NetID = GetNetID($NetMK);
        $qry = "SELECT ADRVALUE FROM NETADDRESS WHERE NODEID = '{$MK}' AND NETID = '{$NetID}'";
        $recs = ibase_query($configdb,$qry);
        while ($rec = ibase_fetch_object($recs)) 
            $Res = $rec->ADRVALUE;
    } 
    else {
        $qry = "SELECT ABNNO FROM ASTREE WHERE NODEID = '{$MK}'";
        $recs = ibase_query($configdb,$qry);
        while ($rec = ibase_fetch_object($recs)) 
            $Res = $rec->ABNNO;
    }
    if ($FPOCodeNo <3)   //not IsUNO
        $Res = 'zero';
    return $Res;
}

function GetDeviceType ($MK) {
    global $configdb;
    $Res = '';
    $qry = "SELECT CATEGORY FROM ASTREE WHERE NODEID = '{$MK}'";
    $recs = ibase_query($configdb,$qry);
    while ($rec = ibase_fetch_object($recs))
        $cat = $rec->CATEGORY;
    if ($cat == '3') {
        $qry = "SELECT * FROM ISNETCONTROLLER('{$MK}')"; //вызов и вывод ХП
        $prep = ibase_prepare($qry);
        $rs = ibase_execute ($prep);
        $row = ibase_fetch_row($rs);
        $ProcRes = $row[0];
        if ($ProcRes >0) 
            $Res = 'UNO';
        else {
            $qry = "SELECT T1.NAME FROM ASTREE T, ASTREE T1 WHERE T.NODEID='{$MK}' AND T1.NODEID = T.PARENTID";
            $recs = ibase_query($configdb,$qry);
            while ($rec = ibase_fetch_object($recs)) 
                $ParentMKName = $rec->NAME;
            $ParCheck = str_split($ParentMKName,2);
            if ($ParCheck[0] == 'WS') 
                $Res = 'RS';
            else 
                $Res = 'MK';
            
        }
    } 
    else if ($cat = '4') 
        $Res = 'MK';
    return $Res;
}

function GetTakt($MK) {
    global $configdb;
    $Res='';
    $qry = "SELECT SENSCOUNT FROM ASTREE WHERE NODEID ='{$MK}'"; 
    $recs = ibase_query($configdb,$qry);
    while ($rec = ibase_fetch_object($recs)) 
        $Res = $rec->SENSCOUNT;
    if ($Res == '') 
        $Res = '0';
    return $Res;
}

function GetMK ($PinMK) { //SELECT из LINK намеренно оставил в таком виде, чтобы не выносить определение МК в общий код. Так красивее и понятнее 
    global $configdb;
    $MK = array();
    $MKChildID =' ';
    $qry = "SELECT FINNODEID FROM LINK WHERE STARTNODEID = '{$PinMK['NODEID']}'";
    $recs = ibase_query($configdb,$qry);
    if (empty($recs->FINNODEID)) { //SELECT получил пустоту
        $qry = "SELECT STARTNODEID FROM LINK WHERE FINNODEID = '{$PinMK['NODEID']}'";
        $InsRecs = ibase_query($configdb,$qry);
        while ($InsRec = ibase_fetch_object($InsRecs)) 
            $MKChildID = $InsRec->STARTNODEID;
    }  
    if ($MKChildID == ' ') 
        while ($rec = ibase_fetch_object($recs)) 
            $MKChildID = $rec->FINNODEID;
    if ($MKChildID <>' ') {
        $qry = "SELECT T.PARENTID, T1.NAME FROM ASTREE T, ASTREE T1 WHERE T.NODEID = '{$MKChildID}' AND T1.NODEID = T.PARENTID";
        $recs = ibase_query($configdb,$qry);
        while ($rec = ibase_fetch_object($recs)) {
            $MK['NODEID'] = $rec -> PARENTID;
            $MK['NAME'] = $rec -> NAME;
        }
    }
    return $MK;
}

        
?>
