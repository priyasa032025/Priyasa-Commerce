<?php
namespace Modules\PriyasaCore\Models;
use Modules\PriyasaCore\Models\PriyasaModel;
class CommerceConversationMessage extends PriyasaModel {
 protected $table='priyasa_commerce_conversation_messages';
 protected $guarded=[];
 protected $casts=['metadata'=>'array'];
 public function conversation(){return $this->belongsTo(CommerceConversation::class,'conversation_id');}
}
