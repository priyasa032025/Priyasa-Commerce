<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class CommerceConversation extends PriyasaModel {
 protected $table='priyasa_commerce_conversations';
 protected $guarded=[];
 protected $casts=['metadata'=>'array','last_message_at'=>'datetime'];
 public function messages(){return $this->hasMany(CommerceConversationMessage::class,'conversation_id');}
}
