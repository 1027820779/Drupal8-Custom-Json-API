<?php
namespace Drupal\article_json\Controller;

use Drupal\Core\Database\Database;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\node\Entity\Node;
use Drupal\file\FileInterface;


class ArticleJsonController {

    /**
     * @param $ID 文章id
     * @return JsonResponse 含词汇和文章内容的json数据
     */

    public function index($ID){

        $node = Node::load($ID);
        if(!empty($node)){
            $type=$node->getType();
            $length=count($node->get("field_word"));
            if($type=="article_relate"){
                $i=0;
                $body=$node->get("field_article")->value;
                $title=$node->get("title")->value;
                $all_word=array();
                $all_data=array();
                if($length!=0){
                    for($i;$i<$length;$i++){
                        $id=$node->get("field_word")[$i]->target_id;
                        //调用 search_word() 函数
                        $all_word[]=$this->search_word($id);
                        //调用 article_handle() 函数
                        $body=$this->article_handle($this->search_word($id),$body);
                    }
                }else{
                    $all_word='null';
                }

                if(preg_match_all('/(\[).+?(\])/im',$body,$m)){
                    $body=preg_replace('/(\[).+?(\])/im', '',$body);
                }


                //add <p>....</p> element
                preg_match_all('/\b\w.+\B/m', $body, $matches);
                foreach($matches[0] as $k=>$v){
                    $new_v="<p>".$v."</p>";
                    $body=str_replace($v,$new_v,$body);
                }

                $all_data["message"]['title']=$title;
                $all_data["message"]['article']=$body;
                $all_data["vocabulary"]=$all_word;

                return new JsonResponse($all_data);
            }else{
                return new JsonResponse("null");
            }
        }else{
            return new JsonResponse("null");
        }
    }


    /**
     * @return JsonResponse  artile list json
     */
    public function article_list(){

        //获取数据
        $connection = Database::getConnection();
        $sth = $connection->select('node', 'x')
            ->fields('x', array('nid', 'type'));
        $data = $sth->execute();
        $results = $data->fetchAll(\PDO::FETCH_OBJ);


        $article_arr=array();
        foreach($results as $k=>$v){
            if($v->type=='article_relate'){
                $node = Node::load($v->nid);
                $title=$node->get("title")->value;
                $arr=array(
                    'nid'=>$v->nid,
                    'title'=>$title
                );
                $article_arr[]=$arr;
            }
        }

        return new JsonResponse($article_arr);
    }





    /**
     * 回调函数
     * 实现高亮单词，放a标签，加id
     * @param $word_arr 词汇数组
     * @param $body 文章内容
     * @return mixed 已处理好的文章内容
     */

    public function article_handle($word_arr,$body){
        $reference=$word_arr['reference'];
        if($reference==''){
            $spelling=$word_arr['spelling'];
        }else{
            $sin=strpos($reference,'(');
            $ein=strpos($reference,')');
            $Len=$ein-$sin;
            $spelling=substr($reference,$sin+1,$Len-1);
        }
        $uid_str='<span  id="'.$word_arr['uuid'].' " class="learnWord">'.$spelling.'</span>  ';
        $pattern = "/\b{$spelling}\b/im";
        $body=preg_replace($pattern, $uid_str,$body,1);
        return $body;
    }

    /**
     * 回调函数
     * 处理词汇
     * @param $id 单词id
     * @return array 已格式化的数组
     */

    public function search_word($id){
        $node = Node::load($id);
        $arr=array();
        //拼写
        $arr['spelling']=$node->get("field_vocabulary")->spelling;
        //音标
        $arr["pronunciation"]=$node->get("field_vocabulary")->pronunciation;

        //发音
        $fid=$node->get("field_vocabulary")->audio;
        $file=File::load($fid);
        //$path = file_create_url($file->getFileUri());
        if(is_null($file)){
            $path = 'null';
        }else{
            $path = $file->url();
        }
        //             https|https+域名          +      根目录       +   音频目录  +  音频文件名
        $arr["audio"]='http://'.$_SERVER['HTTP_HOST'].'/houdun/drupal'.'/Audio/'.$node->uuid().'.mp3';

        //意思
        $arr["meaning"]=$node->get("field_vocabulary")->meaning;
        //词性
        $arr["partOfSpeech"]=$node->get("field_vocabulary")->partofspeech;
        //例句
        $arr["sentence"]=$node->get("field_vocabulary")->sentence;
        //单词变体
        $arr["reference"]=$node->get("field_vocabulary")->reference;
        //uuid
        $arr["uuid"]=$node->uuid();
        $arr["id"]=$id;

        return $arr;
    }


}

