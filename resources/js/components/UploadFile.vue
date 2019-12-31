<template>
<div>
    <input type="file" @change="selectFile">
</div>
</template>

<script>
    export default {
        name: "UploadFile",
        data(){
            return {
                file:null,
                chunk_index:0,
                chunk_size:0,
                chunk_total:0,
                resource_uuid:null,
            }
        },
        props:{
            group:{type: String, required:true,},//分组
            preprocess_url:{type: String, required:true,},//预处理地址
            upload_url:{type: String, required:true,},//上传地址
            auto:{type: Boolean, required:false,default:true},//是否自动上传

            retry_num:{default:3}, //每个切片重试次数
            access_token:{type:String,required:true,} //token
        },
        methods:{
            selectFile(e){
                if (!e.target.files[0].size) return;
                this.file=e.target.files[0];
                this.$emit('upload_before',this.file);
                if(this.auto){this.preprocess();}
            },
            /**
             * 预处理
             */
            preprocess(){
                axios.post(this.preprocess_url,{
                    resource_mime_type:this.file.type,
                    resource_name: this.file.name,
                    resource_size: this.file.size,
                    group: this.group,
                },{
                    headers:{"Access-Token":this.access_token},
                }).then(res=>{
                    if(res.data.code===1){
                        this.chunk_size=res.data.data.chunk_size;
                        this.chunk_total=res.data.data.chunk_total;
                        this.resource_uuid=res.data.data.resource_uuid;
                        this.upload()
                    }
                }).catch(err=>{
                    console.error(err);
                });
            },
            /**
             * @param curr_retry_num 当前重试次数
             */
            upload(curr_retry_num=1){
                let formData = new FormData();
                let start = this.chunk_index * this.chunk_size, end = Math.min(this.file.size, start + this.chunk_size);
                formData.append('resource_chunk', this.file.slice(start, end));
                formData.append('chunk_index', this.chunk_index+1);
                formData.append('group', this.group);

                axios.post(this.upload_url+'/'+this.resource_uuid,formData,{
                    headers:{"Access-Token":this.access_token},
                }).then(res=>{
                    //钩子
                    this.uploadProcess(1);
                    if(res.data.code===0){console.error(res.data.msg);return;}
                    if (res.data.data.resource_uuid) {
                        this.uploadSuccess();
                    }else {
                        this.chunk_index++;
                        this.upload();
                    }
                }).catch(err=>{
                    console.error(err);
                    this.uploadProcess(0);
                    //重试
                    console.log(curr_retry_num);
                    if(curr_retry_num<this.retry_num){
                        this.upload(++curr_retry_num);
                    }else {
                        this.uploadError();
                    }
                });
            },
            /**
             * 上传中 钩子
             * @param status 状态0或1
             */
            uploadProcess(status){
                this.$emit('upload_process',{chunk_index: this.chunk_index+1,chunk_total:this.chunk_total, upload_status:status,resource_uuid:this.resource_uuid});
            },
            /**
             * 上传成功 钩子
             */
            uploadSuccess(){
                this.$emit('upload_success',this.resource_uuid);
            },
            /**
             * 上传失败 钩子
             */
            uploadError(){
                this.$emit('upload_error',this.resource_uuid);
            }
        },
        created(){

        }
    }
</script>

<style scoped>

</style>
