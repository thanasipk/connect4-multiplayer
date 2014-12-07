<?php

class Board extends CI_Controller {

  $NUM_COLUMNS = 7; 
  $NUM_ROWS = 6;

  function __construct() {
    // Call the Controller constructor
    parent::__construct();
    session_start();
  }

  public function _remap($method, $params = array()) {
    // enforce access control to protected functions
    $protected = array('index', 'postMsg', 'getMsg');

    if (in_array($method,$protected) && !isset($_SESSION['user'])) {
      $this->session->set_flashdata('warning', 'You need to sign in first!');
      redirect('account/loginForm', 'refresh');
    }

    return call_user_func_array(array($this, $method), $params);
  }

  function index() {
    $user = $_SESSION['user'];

    $this->load->model('user_model');
    $this->load->model('invite_model');
    $this->load->model('match_model');

    $user = $this->user_model->get($user->login);
    $invite = $this->invite_model->get($user->invite_id);

    if ($user->user_status_id == User::WAITING) {
      $otherUser = $this->user_model->getFromId($invite->user2_id);
    }
    else if ($user->user_status_id == User::PLAYING) {
      $match = $this->match_model->get($user->match_id);
      if ($match->user1_id == $user->id)
        $otherUser = $this->user_model->getFromId($match->user2_id);
      else
        $otherUser = $this->user_model->getFromId($match->user1_id);
    }

    $data = array(
      'title' => 'Connect 4 game area',
      'main' => 'match/board',
      'user' => $user,
      'otherUser' => $otherUser
    );

    switch($user->user_status_id) {
      case User::PLAYING:	
        $data['status'] = 'playing';
        break;
      case User::WAITING:
        $data['status'] = 'waiting';
        break;
    }

    $this->load->view('template', $data);
  }

 	function postMsg() {
 		$this->load->library('form_validation');
 		$this->form_validation->set_rules('msg', 'Message', 'required');
 		
 		if ($this->form_validation->run() == TRUE) {
 			$this->load->model('user_model');
 			$this->load->model('match_model');

 			$user = $_SESSION['user'];
 			 
 			$user = $this->user_model->getExclusive($user->login);
 			if ($user->user_status_id != User::PLAYING) {	
				$errormsg="Not in PLAYING state";
 				goto error;
 			}
 			
 			$match = $this->match_model->get($user->match_id);			
 			
 			$msg = $this->input->post('msg');
 			
 			if ($match->user1_id == $user->id)  {
 				$msg = $match->u1_msg == ''? $msg :  $match->u1_msg . "\n" . $msg;
 				$this->match_model->updateMsgU1($match->id, $msg);
 			}
 			else {
 				$msg = $match->u2_msg == ''? $msg :  $match->u2_msg . "\n" . $msg;
 				$this->match_model->updateMsgU2($match->id, $msg);
 			}
 				
 			echo json_encode(array('status'=>'success'));
 			 
 			return;
 		}
		
 		$errormsg="Missing argument";
 		
		error:
			echo json_encode(array('status'=>'failure','message'=>$errormsg));
 	}
 
	function getMsg() {
 		$this->load->model('user_model');
 		$this->load->model('match_model');
 			
 		$user = $_SESSION['user'];
 		 
 		$user = $this->user_model->get($user->login);
 		if ($user->user_status_id != User::PLAYING) {	
 			$errormsg="Not in PLAYING state";
 			goto error;
 		}
 		// start transactional mode  
 		$this->db->trans_begin();
 			
 		$match = $this->match_model->getExclusive($user->match_id);			
 			
 		if ($match->user1_id == $user->id) {
			$msg = $match->u2_msg;
 			$this->match_model->updateMsgU2($match->id,"");
 		}
 		else {
 			$msg = $match->u1_msg;
 			$this->match_model->updateMsgU1($match->id,"");
 		}

 		if ($this->db->trans_status() === FALSE) {
 			$errormsg = "Transaction error";
 			goto transactionerror;
 		}
 		
 		// if all went well commit changes
 		$this->db->trans_commit();
 		
 		echo json_encode(array('status'=>'success','message'=>$msg));
		return;
		
		transactionerror:
		$this->db->trans_rollback();
		
		error:
		echo json_encode(array('status'=>'failure','message'=>$errormsg));
 	}

  /* Checks for a horizontal sequence of a player's chips */
  function check_horizontal($matrix, $player) {
    for ($row = 0; $row < $NUM_ROWS; $row++) {
      for ($column = 0; $column < $NUM_COLUMNS; $column++) {      
        if (
          $matrix[$row][$column] == $player &&
          $matrix[$row][$column + 1] == $player &&
          $matrix[$row][$column + 2] == $player &&
          $matrix[$row][$column + 3] == $player) {
            return true;
        }
      } // End foreach row as column
    } // End foreach matrix as row
    return false;
  }

  /* Checks for a vertical sequence of a player's chips */
  function check_vertical($matrix, $player) {
    foreach($matrix as $row) {
      $win_count = 0;
      foreach($row as $column) {
        if (
          $matrix[$row][$column] == $player &&
          $matrix[$row + 1][$column] == $player &&
          $matrix[$row + 2][$column] == $player &&
          $matrix[$row + 3][$column] == $player) {
            return true;
        }
      } // End foreach row as column
    } // End foreach matrix as row
    return false;
  } // End check_vertical

  /* Checks for a diagonal sequence of a player's chips
  NOT FINISHED
   */
  function check_diagonal($matrix, $player) {
    foreach($matrix as $row) {
      $win_count = 0;
      foreach($row as $column) {
        if($matrix[$row + $NUM_ROWS][$column + 1] == $player) {
          $win_count++;  
        }
        else {
          $win_count = 0;
        } 
      } // End foreach row as column
    } // End foreach matrix as row
  } // End check_diagonal
}