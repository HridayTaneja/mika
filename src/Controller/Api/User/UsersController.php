<?php
namespace App\Controller\Api\User;

use App\Controller\Api\User\ApiController;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Core\Exception\Exception;
use Cake\Network\Exception\NotFoundException;
use Cake\Network\Exception\UnauthorizedException;
use Cake\Auth\DefaultPasswordHasher;
use Firebase\JWT\JWT;
use Cake\Utility\Security;
use Cake\I18n\Time;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Collection\Collection;

/**
 * Users Controller
 *
 *
 * @method \App\Model\Entity\User[] paginate($object = null, array $settings = [])
 */
class UsersController extends ApiController
{

    public function initialize()
    {
        parent::initialize();
        $this->Auth->allow(['login','socialLogin','socialSignup','add']);
    }

    public function socialSignup($reqData){

          $displayName = preg_split('/\s+/', $reqData['displayName']);
          
          $data = [
                      'first_name' => $displayName[0],
                      'last_name' => $displayName[1],
                      'email' => ($reqData['email'])?$reqData['email']:'',
                      'phone' => ($reqData['phoneNumber'])?$reqData['phoneNumber']:'',
                      'password' => '123456789',
                      'role_id' => 3,
                      'username' => $reqData['email']
                  ];
          $data['social_connections'][] = [
                                          'fb_identifier' => $this->request->data['uid'],
                                          'status' => 1
                                        ];
          $data['experts'] = [[]];
          $user = $this->Users->newEntity();
          $user = $this->Users->patchEntity($user, $data, ['associated' => ['Experts','SocialConnections']]);

            if (!$this->Users->save($user)) {
            
            if($user->errors()){
              $this->_sendErrorResponse($user->errors());
            }
            throw new Exception("Error Processing Request");
          }

        return $user->id;
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     */
    public function add()
    {     

        if(!$this->request->is(['post'])){
            throw new MethodNotAllowedException(__('BAD_REQUEST'));
        }
        $user = $this->Users->newEntity();
        $data = $this->request->getData();
        
        if(isset($data['email']) && $data['email']){
          $data['username'] = $data['email'];
        }
        $data['role_id'] = 2;

        $user = $this->Users->patchEntity($user, $data);
        
        if (!$this->Users->save($user)) {
          
          if($user->errors()){
            $this->_sendErrorResponse($user->errors());
          }
          throw new Exception("Error Processing Request");
        }
        
        $success = true;

        $this->set(compact('user','success'));
        $this->set('_serialize', ['user','success']);
    }

    public function edit($id = null)
    {

        if(!$this->request->is(['post','put'])){
            throw new MethodNotAllowedException(__('BAD_REQUEST'));
        }
        
        $user = $this->Auth->user();
        
        if($user['role_id'] != 2){
           throw new UnauthorizedException(__('You are not authorized to access that location'));
        }
        $user = $this->Users->get($user['id'], [
            'contain' => []
        ]);
        
        $user = $this->Users->patchEntity($user, $this->request->getData());
        
        if (!$this->Users->save($user)) {
            throw new Exception("User edits could not be saved.");
        }
        
        $this->set(compact('user'));
        $this->set('_serialize', ['user']);
    }

    public function socialLogin(){

      if (!$this->request->is(['post'])) {
        throw new MethodNotAllowedException(__('BAD_REQUEST'));
      }
      
      $this->loadModel('SocialConnections');
      $socialConnection = $this->SocialConnections->find()->where(['fb_identifier' => $this->request->data['uid']])->first();


      if(!$socialConnection){
        $userId = $this->socialSignup($this->request->data);

      }else{
        $userId = $socialConnection->user_id;
      }

      $data =array();            
      $user = $this->Users->find()
                          ->where(['id' => $userId])
                          ->contain(['SocialConnections'])
                          ->first();     
      
      if (!$user) {
        throw new NotFoundException(__('LOGIN_FAILED'));
      }

      if ($user->role_id != 2) {
        throw new NotFoundException(__('You are not a user of this application.'));
      }
      $time = time() + 10000000;
      $expTime = Time::createFromTimestamp($time);
      $expTime = $expTime->format('Y-m-d H:i:s');
      $data['status']=true;
      $data['data']['user']=$user;
      $data['data']['token']=JWT::encode([
        'sub' => $user['id'],
        'exp' =>  $time,
        'expert_id'=>$user['experts'][0]['id'],
        ],Security::salt());
      $data['data']['expires']=$expTime;
      $this->set('data',$data['data']);
      $this->set('status',$data['status']);
      $this->set('_serialize', ['status','data']);

    }

     public function login(){
     
      if (!$this->request->is(['post'])) {
        throw new MethodNotAllowedException(__('BAD_REQUEST'));
      }
      
      $data =array();
      $user = $this->Auth->identify();
      if (!$user) {
        throw new NotFoundException(__('LOGIN_FAILED'));
      }
      $user = $this->Users->find()
                            ->where(['id' => $user['id']])
                            ->first();

      $time = time() + 10000000;
      $expTime = Time::createFromTimestamp($time);
      $expTime = $expTime->format('Y-m-d H:i:s');
      $data['status']=true;
      $data['data']['user']=$user;
      $data['data']['token']=JWT::encode([
        'sub' => $user['id'],
        'exp' =>  $time,
        'expert_id'=>$user['experts'][0]['id'],
        ],Security::salt());
      $data['data']['expires']=$expTime;
      $this->set('data',$data['data']);
      $this->set('status',$data['status']);
      $this->set('_serialize', ['status','data']);
    }
}
