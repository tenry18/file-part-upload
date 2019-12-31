<template>
<div>
    <input type="file" @change="selectFile" :multiple="multiple">
</div>
</template>

<script>
    export default {
        name: "UploadFile",
        data(){
            return {
                files:[],//多文件
                upload_success_num:0,//上传成功计数
            }
        },
        watch:{

        },
        props:{
            group:{type: String, required:true,},//分组
            preprocess_url:{type: String, required:true,},//预处理地址
            upload_url:{type: String, required:true,},//上传地址
            auto:{type: Boolean, required:false,default:true},//是否自动上传
            retry_num:{default:3}, //每个切片重试次数

            multiple:{type: Boolean,default:true},//是否允许多文件
            access_token:{type:String,required:true,} //token
        },
        methods:{
            selectFile(e){
                this.files=e.target.files;
                this.$emit('upload_before',this.files);
                if(this.auto){
                    for(let i=0;i<this.files.length;i++){
                        this.preprocess(this.files[i]);
                    }
                }
            },
            /**
             * 预处理
             */
            preprocess(file){
                axios.post(this.preprocess_url,{
                    resource_mime_type:file.type,
                    resource_name: file.name,
                    resource_size: file.size,
                    group: this.group,
                },{
                    headers:{"Access-Token":this.access_token},
                }).then(res=>{
                    if(res.data.code===1){
                        file.chunk_size=res.data.data.chunk_size;
                        file.chunk_total=res.data.data.chunk_total;
                        file.chunk_index=0;
                        file.resource_uuid=res.data.data.resource_uuid;
                        this.upload(file)
                    }
                }).catch(err=>{
                    console.error(err);
                });
            },
            /**
             * @param file 文件
             * @param curr_retry_num 当前重试次数
             */
            upload(file,curr_retry_num=1){
                let formData = new FormData();
                let start = file.chunk_index * file.chunk_size, end = Math.min(file.size, start + file.chunk_size);
                formData.append('resource_chunk', file.slice(start, end));
                formData.append('chunk_index', file.chunk_index+1);
                formData.append('group', this.group);


//                 var request = new XMLHttpRequest();
//                 // request.open("POST",this.upload_url+'/'+file.resource_uuid);
//                 request.open("POST",'http://192.168.2.200:9502/'+file.resource_uuid);
//                 request.setRequestHeader('Access-Token',this.access_token);
//                 request.onload = function(oEvent) {
//                     console.log(request);
//                 };
//                 request.send(formData);
//
//
//
//
// console.log(formData.getAll('resource_chunk'));return;
                axios.post(this.upload_url+'/'+file.resource_uuid,formData,{
                    headers:{"Access-Token":this.access_token},
                }).then(res=>{
                    this.uploadChunkProcess(file,1);//块上传钩子
                    if(res.data.code===0){console.error(res.data.msg);return;}

                    if (res.data.data.resource_uuid) {
                        this.uploadFileSuccess(file);//文件钩子

                    }else {
                        file.chunk_index++;
                        this.upload(file);
                    }
                }).catch(err=>{
                    console.error(err);
                    this.uploadChunkProcess(file,0);
                    if(curr_retry_num<this.retry_num){
                        this.upload(file,++curr_retry_num);
                    }else {
                        this.uploadFileError(file);//文件失败钩子
                    }
                });
            },
            /**
             * 切片上传中 钩子
             * @param file
             * @param status 状态0或1
             */
            uploadChunkProcess(file,status){
                this.$emit('upload_chunk_process',{chunk_index: file.chunk_index+1,chunk_total:file.chunk_total, chunk_upload_status:status,resource_uuid:file.resource_uuid});
            },
            /**
             * 文件上传成功 钩子
             */
            uploadFileSuccess(file){
                file.upload_status=1;
                ++this.upload_success_num;
                this.$emit('upload_file_success',file.resource_uuid);
                if (this.upload_success_num === this.files.length) {
                    this.uploadSuccess();
                }
            },
            /**
             * 文件上传失败 钩子
             */
            uploadFileError(file){
                file.upload_status=0;
                this.$emit('upload_file_error',file.resource_uuid);

                this.uploadError();
            },
            /**
             * 上传成功
             */
            uploadSuccess(){
                this.$emit('upload_success',this.files);
            },
            /**
             * 上传失败
             */
            uploadError(){
                this.$emit('upload_error',this.files);
            }
        },
        created(){

        }
    }
</script>

<style scoped>

</style>
