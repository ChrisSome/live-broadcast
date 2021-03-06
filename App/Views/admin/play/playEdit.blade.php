@extends('admin.auth.playBase')

@section('javascriptFooter')
    <script>

        layui.use('form', function(){
            var form = layui.form;

            form.val("form", {
                "name": "{{ $info['name'] }}"
                ,"url": "{{ $info['url'] }}"
                ,"func": "{{ $info['func'] }}"
                ,"status": {{ $info['status'] }}
            });


            //监听提交
            form.on('submit(submit)', function(data){
                data.field.status = data.field.status ? 1 : 0;
                $.post('/core/play/edit/'+{{ $info['id'] }},data.field,function(info){
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