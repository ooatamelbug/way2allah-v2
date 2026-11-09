{{-- OLD AdvancedStats() + advanced_stats.htm (modules/Khotab/index.php:893): the read-only
     «إحصائيات متطورة» block every administrator saw on the item page. Rendered only inside
     @contextualAdmin; KhotabItemController marks an admin's response private. --}}
@if ($stats !== [])
    <div class="w2a-admin-ctx w2a-khotab-advanced-stats" dir="rtl" data-contextual-admin data-advanced-stats style="display:block">
        <span class="w2a-admin-ctx__title"><i class="fa fa-bar-chart" aria-hidden="true"></i> إحصائيات متطورة</span>
        <div class="row" style="margin:6px 0 0">
            <div class="col-sm-4">
                <h4>تقرير النسخ الإحتياطي</h4>
                <p>إنزال نسخة إحتياطية : <font color="#800000">{{ $stats['down'] }}</font>
                    <br>حجم النسخة الإحتياطية : <font color="#800000">{{ $stats['linksize'] }}</font>
                    <br>القرص المدمج : <font color="#800000">{{ $stats['cd'] }}</font></p>
            </div>
            <div class="col-sm-4">
                <h4>تقرير صيانة الوصلات</h4>
                <p>حالة الوصلة : <font color="#800000">{{ $stats['online'] }}</font>
                    <br>تاريخ الفحص : <font color="#800000">{{ $stats['checktime'] }}</font>
                    <br>الحجم عند الفحص : <font color="#800000">{{ $stats['checksize'] }}</font>
                    <br>إبلاغ الأعضاء عن رابط لا يعمل : <font color="#800000">{{ $stats['broken'] }}</font></p>
            </div>
            <div class="col-sm-4">
                <h4>معلومات عن المادة</h4>
                <p>مدة العرض : <font color="#800000">{{ $stats['adur'] }}</font>
                    <br>حقوق النشر : <font color="#800000">{{ $stats['cright'] }}</font>
                    <br>التعليق : <font color="#800000">{{ $stats['perf'] }}</font></p>
            </div>
            @if ($stats['audio'] !== null)
                <div class="col-sm-4">
                    <h4>خصائص الصوت</h4>
                    <p>معدل الصوت : <font color="#800000">{{ $stats['audio']['srate'] }}&nbsp; هرتز</font>
                        <br>جودة الصوت : <font color="#800000">{{ $stats['audio']['ares'] }}&nbsp; بت</font>
                        <br>البت ريت للصوت : <font color="#800000">{{ $stats['audio']['abit'] }}&nbsp; كيلو بت</font>
                        <br>كوديك الصوت : <font color="#800000">{{ $stats['audio']['alist'] }}</font></p>
                </div>
            @endif
            @if ($stats['video'] !== null)
                <div class="col-sm-4">
                    <h4>خصائص الصورة</h4>
                    <p>معدل الفرامات/ثانية : <font color="#800000">{{ $stats['video']['frate'] }}&nbsp; فرام</font>
                        <br>جودة الصورة : <font color="#800000">{{ $stats['video']['vres'] }}&nbsp; بت</font>
                        <br>البت ريت للصورة : <font color="#800000">{{ $stats['video']['vbit'] }}&nbsp; كيلو بت</font>
                        <br>أبعاد الصورة : <font color="#800000">{{ $stats['video']['width'] }} بكسل &nbsp;في&nbsp; {{ $stats['video']['height'] }}&nbsp; بكسل</font>
                        <br>كوديك الصورة : <font color="#800000">{{ $stats['video']['vlist'] }}</font></p>
                </div>
            @endif
        </div>
    </div>
@endif
