@extends('admin.auth.postBase')

@section('body-title')
    <div class="layui-card-header">审核帖子</div>
@endsection
@section('javascriptFooter')
    <script src="/layui/layui.all.js"></script>
    <script>
        //展示下拉框
        layui.use('form', function(){
            var layedit = layui.layedit;

            layedit.set({
                uploadImage: {
                    url: '/upload' //接口url
                    ,type: 'post' //默认post
                }
            });
            var _index = layedit.build('content_message');
            var form = layui.form;
            var form_field;
            form.val("form", {
                "title": "{{ $info['title'] }}"
                ,"remark": "{{ $info['remark'] }}"
            });

            function callback(data) {
                if(data.code != 0) {
                    layer.msg(info.msg);
                } else {
                    layer.msg('编辑成功', {time:1000}, function(){
                        form_field.form.reset();
                        location.href='/setting/message'
                    });
                }
            }

            //监听提交
            form.on('submit(submit)', function(data){
                form_field = data;
                data.field.content = layui.layedit.getContent(_index);
                $.post('/user/post/edit/'+{{ $info['id'] }},data.field,function(info){
                    if(info.code != 0) {
                        layer.msg(info.msg);
                    } else {
                        layer.msg('编辑成功',{time:1000},function(){
                            parent.layer.close(parent.layer.getFrameIndex(window.name));
                            parent.refresh();
                        });
                    }
                });

                return false;
            });
        });
    </script>
@endsection