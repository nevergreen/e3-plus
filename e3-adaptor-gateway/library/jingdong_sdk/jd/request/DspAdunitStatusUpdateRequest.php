<?php
class DspAdunitStatusUpdateRequest
{
	private $apiParas = array();
	
	public function getApiMethodName(){
	  return "jingdong.dsp.adunit.status.update";
	}
	
	public function getApiParas(){
	    if(empty($this->apiParas)){
            return "{}";
        }
        return json_encode($this->apiParas);
	}
	
	public function check(){
		
	}
	
	public function putOtherTextParam($key, $value){
		$this->apiParas[$key] = $value;
		$this->$key = $value;
	}

    private  $version;

    public function setVersion($version){
        $this->version = $version;
    }

    public function getVersion(){
        return $this->version;
    }
                                                        		                                    	                        	                   			private $status;
    	                        
	public function setStatus($status){
		$this->status = $status;
         $this->apiParas["status"] = $status;
	}

	public function getStatus(){
	  return $this->status;
	}

                                                 	                        	                                                                                                                                                                                                                                                                                                               private $ids;
                              public function setIds($ids ){
                 $this->ids=$ids;
                 $this->apiParas["ids"] = $ids;
              }

              public function getIds(){
              	return $this->ids;
              }
                                                                                                                                                                    	}





        
 

