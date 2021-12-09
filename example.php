function PartnerResolution($sWorkOrderID, $sDealID) {		
		$arAccessData = self::getAccessData();
		$obEnum = new CUserFieldEnum;
		$arDealFilter = array(
			'UF_CRM_1567673095855' => $sWorkOrderID,
			'CHECK_PERMISSIONS' => 'N',
		);
		$arDealSelect = array(
			'ID',
			'UF_CRM_1552561614631',
			'UF_CRM_1567585981946',
			'UF_CRM_1567585509075',
			'UF_CRM_1567585884156',
			'UF_CRM_1548071575984',
			'UF_CRM_1567586084563',
		);
		$arDealRes = CCrmDeal::GetListEx(array('ID'=>'DESC'), $arDealFilter, false, false, $arDealSelect, false);
		if($arDeal = $arDealRes->Fetch()) {
			$arTaskFilter = array(
				'UF_CRM_TASK' => 'D_' . $arDeal['ID'],
				'CHECK_PERMISSIONS' => 'N',
			);
			$arTaskRes = CTasks::GetList(array('ID'=>'DESC'), $arTaskFilter, array('ID', 'DATE_START', 'CLOSED_DATE'), array());
			if($arTask = $arTaskRes->Fetch()) {
				if(!empty($arTask['DATE_START'])) {
					$sFormatDate = DateTime::createFromFormat('d.m.Y H:i:s', $arTask['DATE_START']);
					if(!empty($sFormatDate)) {
						$sStartTask = $sFormatDate->format('Y-m-d') . 'T' . $sFormatDate->format('H:i:s');
					}
					$sStartTaskUnix = strtotime($arTask['DATE_START']);	
				}
				if(!empty($arTask['CLOSED_DATE'])) {
					$sFormatDate = DateTime::createFromFormat('d.m.Y H:i:s', $arTask['CLOSED_DATE']);
					if(!empty($sFormatDate)) {
						$sEndTask = $sFormatDate->format('Y-m-d') . 'T' . $sFormatDate->format('H:i:s');
					}
					$sEndTaskUnix = strtotime($arTask['CLOSED_DATE']);
				}
				if($sEndTaskUnix > 0 && $sStartTaskUnix > 0) {
					$sTaskDuration = $sEndTaskUnix - $sStartTaskUnix;
					$sTaskDuration = (int)round($sTaskDuration/60, 0);
				}
			}
			if(!empty($arDeal['UF_CRM_1552561614631'])) {
				$sResolutionNotes = $arDeal['UF_CRM_1552561614631'];
			}
			if(!empty($arDeal['UF_CRM_1567585981946'])) {
				$sTravelZone = $arDeal['UF_CRM_1567585981946'];
			}
			if(!empty($arDeal['UF_CRM_1567585509075'])) {
				$sRepairClassID = $arDeal['UF_CRM_1567585509075'];
				$rsEnum = $obEnum->GetList(array(), array('ID' => $sRepairClassID));
				$arEnum = array();
				if($arResEnum = $rsEnum->Fetch()) {
					$sRepairClass = $arResEnum['VALUE'];
				}
			}
			if(!empty($arDeal['UF_CRM_1567585884156'])) {
				$sDelayCodeID = $arDeal['UF_CRM_1567585884156'];
				$rsEnum = $obEnum->GetList(array(), array('ID' => $sDelayCodeID));
				$arEnum = array();
				while($arResEnum = $rsEnum->Fetch()) {
					$sDelayCode = $arResEnum['VALUE'];
				}
			}
			if(!empty($arDeal['UF_CRM_1548071575984'])) {
				$sSerialNumber = $arDeal['UF_CRM_1548071575984'];
			}
			if(!empty($arDeal['UF_CRM_1567586084563'])) {
				$sPartNumber = $arDeal['UF_CRM_1567586084563'];
			}
			$arProductRowExtra = array();
			if(CModule::IncludeModule('highloadblock')) {
				$entity_data_class = GetEntityDataClass(1);
				$arFilter = Array(
					'UF_ID_DEAL' => $arDeal['ID'],
				);
				$rsData = $entity_data_class::getList(array(
					'select' => array('UF_DEAL_ARTICLE_NUM', 'UF_POSITION', 'UF_LINE_NUMBER', 'UF_PO_NUMBER'),
					'filter' => $arFilter,
					'order' => array('ID' => 'ASC'),
				));
				while($el = $rsData->fetch()){
					$arProductRowExtra[$el['UF_DEAL_ARTICLE_NUM']]['FAILURE_CODE'] = $el['UF_POSITION'];
					$arProductRowExtra[$el['UF_DEAL_ARTICLE_NUM']]['LINE_NUMBER'] = $el['UF_LINE_NUMBER'];
					$arProductRowExtra[$el['UF_DEAL_ARTICLE_NUM']]['PO_NUMBER'] = $el['UF_PO_NUMBER'];
				}
			}
			$arProductRow = array();
			$arProductRowFilter = array('OWNER_TYPE' => 'D', 'OWNER_ID' => $arDeal['ID']);
			$arRes = CCrmProductRow::GetList(array(), $arProductRowFilter, false, false, array('ID', 'PRODUCT_ID'), array());
			while($arItem = $arRes->GetNext()) {
				$arData = array();
				$arProductFilter = Array(
					'ID' => $arItem['PRODUCT_ID'],
				);
				$arProductSelect = Array('IBLOCK_ID', 'ID', 'NAME', 'PROPERTY_ARTICLE');
				$resProductItems = CIBlockElement::GetList(Array('ID'=>'ASC'), $arProductFilter, false, false, $arProductSelect);
				if($arProductItem = $resProductItems->GetNext()) {
					$arData['ARTICLE'] = $arProductItem['PROPERTY_ARTICLE_VALUE'];
					$arData['NAME'] = $arProductItem['NAME'];
				}
				$arData['FAILURE_CODE'] = $arProductRowExtra[$arItem['ID']]['FAILURE_CODE'];
				$arData['LINE_NUMBER'] = $arProductRowExtra[$arItem['ID']]['LINE_NUMBER'];
				$arData['PO_NUMBER'] = $arProductRowExtra[$arItem['ID']]['PO_NUMBER'];
				$arProductRow[] = $arData;
			}
		}
		$arRepairPart = array();
		foreach($arProductRow as $arRow) {
			$arRepairPart[] = array(
				'LineNumber' => $arRow['LINE_NUMBER'],
				'PartOrderNumber' => $arRow['PO_NUMBER'],
				'RemovedPartNumber' => $arRow['ARTICLE'],
				'RemovedPartDescription' => $arRow['NAME'],
				'FailureCode' => $arRow['FAILURE_CODE'],
			);
		}
		$arRepair = array(
			'RepairClass' => $sRepairClass,
			'DelayCode' => $sDelayCode,
			'SystemSerialNumber' => $sSerialNumber,
			'ServiceStartDateTime' => $sStartTask,
			'ServiceEndDateTime' => $sEndTask,
			'SystemFixedTime' => $sEndTask,
			'SerialNumber' => $sSerialNumber,
			'RepairPart' => $arRepairPart,
			'TimeLog' => array(array('LaborType' => 'Hardware Repair/Installation', 'Duration' => $sTaskDuration)),
		);
		$arResolution = array(
			'TravelZone' => $sTravelZone,
			'ResolutionNotes' => $sResolutionNotes,
			'Repair' => $arRepair,
		);
		$arWorkOrder = array(
			'WorkOrderID' => $sWorkOrderID,
			'PartnerName' => $arAccessData['PARTNER_NAME'],
			'PartnerStatus' => 'Problem Resolution',
			'PartnerStatusDateTime' => date('Y-m-d') . 'T' . date('H:i:s', strtotime('3 hour')),
		);	
		$arCaseExchange = array(
			'EventType' => 'PartnerUpdates',
			'EventSubType' => 'Updates',
			'Originator' => $arAccessData['ORIGINATOR'],
			'IncomingChannel' => $arAccessData['INCOMING_CHANNEL'],
			'WorkOrder' => $arWorkOrder,
			'Resolution' => $arResolution,
		);
		$arJSON['CaseExchange'] = $arCaseExchange;
		$sJSON = json_encode($arJSON, JSON_UNESCAPED_UNICODE);
		$arResponse = self::sendRequest($arAccessData['ACCESS_TOKEN'], $arAccessData['ENDPOINT'], $sJSON);	
		$arResponse = (array)$arResponse['CaseExchange'];
		self::saveRequest($sJSON, $arResponse, $sDealID, 'Problem Resolution');		
}
