<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <script src="{{mix('/js/app.js')}}"></script>
</head>
<body>
<div id="vm">
    视频:<upload-component
          :group="'video'"
          :multiple="true"
          :access_token="'a67af1d60f815a88d2e87665274c93b1'"
          :preprocess_url="'http://192.168.2.201:9501/v3/preprocess'"
          :upload_url="'http://192.168.2.201:9501/v3/uploading'"

          @upload_before="before"
          @upload_process="process"
          @upload_success="success"
          @upload_error="error"
    ></upload-component>

    图片:<upload-component
        :group="'image'"
        :multiple="true"
        :access_token="'a67af1d60f815a88d2e87665274c93b1'"
        :preprocess_url="'http://192.168.2.201:9501/v3/preprocess'"
        :upload_url="'http://192.168.2.201:9501/v3/uploading'"

        @upload_before="before"
        @upload_process="process"
        @upload_success="success"
        @upload_error="error"
    ></upload-component>
</div>
</body>
<script>

new Vue({
    el:'#vm',
    data:{

    },
    methods:{
        before(file){
            console.log(file)
        },
        process(data){
            console.log(data)
        },
        success(files){
            console.info("成功");
            console.log(files)
        },
        error(uuid){
            console.error(uuid)
        }
    },
    created(){

    }
})
</script>
</html>
