<?
$params = "lbd";
include "sintar2007.php";
global $confdb;

$tact_arm = array('100','50','200','');
$fpo_code = array('A','B','C','U');
$MKs = $MSOs = array();

$confdb = ibase_connect($config);
$cont = substr(basename($lbd,'.ib'),3); //Имя контроллера
//Первая строка конфиг-файла:
$qry = "select NodeID,AbnNo,Coeffs,Flags,LBDZoneLens, ParentID from ASTree 
  where Name='{$cont}' and Category=3";  //Category=3 - êîíòðîëëåð
$recs = ibase_query($confdb, $qry);
while ($rec = ibase_fetch_object($recs)) { 
  $NodeID = $rec->NODEID;
  $CrateID = $rec->PARENTID;
  $AbnNo = $rec->ABNNO;
  if ($AbnNo == '')    
    echo "    Íå çàäàí íîìåð àáîíåíòà â ñåòè ÂÓ\n";
  $FPOCodeNo = ((int)$rec->FLAGS/2048)%16;    //Flags div 2048 mod 16;
  $FPOCode = $fpo_code[$FPOCodeNo];
  if ($FPOCode == 'U') 
    if (stripos($cont, 'AU'))
      $FPOCode = 'AU';
    else
      $FPOCode = 'DU';      
  $FPODelay = GetBlob($rec->COEFFS);
  if ($FPODelay == '')    
    echo "    Íå çàäàíà çàäåðæêà âêëþ÷åíèÿ ÔÏÎ\n";
  $ARMSetting = $rec->LBDZONELENS;
  if ($ARMSetting == '')    
    echo "    Íå çàäàíà óñòàâêà äëÿ ÀÐÌ\n";
  $ARMTact = $tact_arm[$FPOCodeNo];
  if ($FPOCodeNo == 3)  //UNO
    $Mode = $Status = '';
  else {
    $Mode = 'NAL';
    $Status = '1';
  }
  echo "REGIM<$Mode>NAME<$cont>TYPE<$FPOCode>TAKT_ARM<$ARMTact>"
    ."UST_ARM<$ARMSetting>SHU_N<$AbnNo>STATUS<$Status>TIMEOUT_FPO<$FPODelay>\n";
}
//Îñòàëüíûå ñòðîêè:
$qry = "select NA.AdrValue,N.NetID,N.Name from Net N, NetAddress NA
  where NA.NodeID=$NodeID and N.NetID=NA.NetID and N.Name containing 'rs'";
$recs = ibase_query($confdb, $qry);
while ($rec = ibase_fetch_object($recs)) { 
  $NetID = $rec->NETID;
  $NetName = $rec->NAME;
  $NetAdr = $rec->ADRVALUE; //num_rs
  if ($NetAdr == '')
    echo "    Íå çàäàí ñåòåâîé àäðåñ â ñåòè $NetName\n"; 
//Ïîëó÷èòü âåðøèíû MK, ñâÿçàííûå ñ âõîäàìè/âûõîäàìè Cont ÷åðåç RS
  $MKs = GetMK(true);
  $MKs = GetMK(false);
  if (count($MKs) > 0) 
    foreach($MKs as $MK)  //Ïîëó÷èòü ÌÑÎ, ñâÿçàííûå ñ ÌÊ 
      $MSOs = GetMSO($MK['CrateID'], $MK['AbnNo']);  
}
//Ïîëó÷èòü ÌÑÎ, ñâÿçàííûå íåïîñðåäñòâåííî ñ ñåòåâûì êîíòðîëëåðîì
$MSOs = GetMSO($CrateID, 0);    


function GetMK($inputs) {
  global $config, $NodeID, $NetID;
  $MK = $Res = array();
  $confdb = ibase_connect($config);
  $qry = "select T2.ParentID, T2.AbnNo, T2.Name
    from ASTree T, ASTree T1, ASTree T2, Link L
    where T.ParentID=$NodeID and L.NetID=$NetID 
      and T2.NodeID=T1.ParentID and T2.Category=3";
  $in  = " and T.NodeID=L.FinNodeID and T1.NodeID=L.StartNodeID";
  $out = " and T.NodeID=L.StartNodeID and T1.NodeID=L.FinNodeID";
  if ($inputs)
    $qry = $qry.$in;
  else  
    $qry = $qry.$out;
  $recs = ibase_query($confdb, $qry);
  while ($rec = ibase_fetch_object($recs)) { 
    $MK['CrateID'] = $rec->PARENTID;
    $MK['AbnNo'] = $rec->ABNNO;
    if ($rec->ABNNO == '')
      echo "    Íå çàäàí íîìåð àáîíåíòà RS êîíòðîëëåðà {$rec->NAME}\n";
    $Res[] = $MK;
  }  
  return $Res;  
} 

function GetMSO($CrateID, $AbnNo) {
  global $config, $FPOCode, $NetAdr;
  $confdb = ibase_connect($config);
  $qry = "select T.Name, T.Adr, T.AbnNo, T.Coeffs, T1.Name as TYPENAME,
      T2.Name as CRATENAME 
    from ASTree T, ASTree T1, ASTree T2 
    where T.ParentID=$CrateID and T.Category=4 and T1.NodeID=T.TypeID
      and T2.NodeID=$CrateID";  
  $recs = ibase_query($confdb, $qry);
  while ($rec = ibase_fetch_object($recs)) {
    $rs_mk = GetBlob($rec->COEFFS);
    if ($rs_mk == '')
      echo "    Íå çàäàí àäðåñ êðåéòà {$rec->CRATENAME}\n"; 
    if ($rec->ADR == '')
      echo "    Íå çàäàíî ïîñàäî÷íîå ìåñòî ÌÑÎ {$rec->NAME}\n";
    if ($rec->ABNNO == '')
      echo "    Íå çàäàí íîìåð àáîíåíòà ÌÑÎ {$rec->NAME}\n";
    echo "NUM_RS<{$NetAdr}>AB_RS<{$AbnNo}>KR<{$rs_mk}>PM<{$rec->ADR}>"
      ."AB_ARM<{$rec->ABNNO}>KOD<{$rec->TYPENAME}>NAME<{$rec->NAME}>\n";
  }
}
?>
