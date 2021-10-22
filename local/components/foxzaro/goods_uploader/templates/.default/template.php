<script
        src="https://code.jquery.com/jquery-2.2.4.min.js"
        integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44="
        crossorigin="anonymous"></script>
<h2>Загрузка файла для обработки (xls,xlsx)</h2>
<div class="updater">
    <form id="uploader_xls" method="post" enctype="multipart/form-data">
        <div>
            <input accept=".xls, .xlsx"  type="file" id="file" name="file">
            <label for="file">Выберите файл</label>
        </div>
        <div>
            <button>Загрузить</button>
        </div>
    </form>
</div>

<script type="application/javascript">
    `use strict`

    $(() => {


        $(`#uploader_xls button`).click((event) => {
            event.preventDefault();

            $("#uploader_xls #file").change(e => {
                $(e.currentTarget).next(`label`).html(e.currentTarget.files[0].name)
            })

            let data = new FormData
            data.append(`DETAIL_URL`, `<?=$componentPath?>`)
            data.append(`FILE`, $("#uploader_xls #file")[0].files[0])

            $.ajax({
                method: `post`,
                url: `<?=$APPLICATION->GetCurDir()?>`,
                data: data,
                contentType: false,
                processData: false,
                success: (data) => {
                    data = JSON.parse(data)
                    if (data.status === true) {
                        $(".updater").html("<p style='color:green'>"+data.message+"</p>")

                    } else {
                        $(".updater").html("<p style='color:red'>"+data.message+"</p>")
                    }
                    setTimeout(function(){
                        window.location.href = '/';
                    }, 2.5 * 1000);
                }
            })
        })
    })
    $(function () {
        $("[type=file]").on("change", function () {
            var file = this.files[0];
            var formdata = new FormData();
            formdata.append("file", file);
            if (file.name.length >= 30) {
                $("label").text("Выбран : " + file.name.substr(0, 30) + "..");
            } else {
                $("label").text("Выбран : " + file.name);
            }
        });
    });

</script>