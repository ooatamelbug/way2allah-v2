var TableDatatablesScroller = function() {
    var e = function() {
            var e = $("#tabelgrp");	
            e.dataTable({
                language: {
                    aria: {
                        sortAscending: ": activate to sort column ascending",
                        sortDescending: ": activate to sort column descending"
                    },
                    emptyTable: "لا يوجد مجموعات",
                    info: "عرض _START_ إلى _END_ من _TOTAL_ مجموعة",
                    infoEmpty: "لم يتم العثور على إي مجموعة مطابقة",
                    infoFiltered: "(مجموعة مختارة من _MAX_ كعدد كلي)",
                    lengthMenu: "_MENU_ مجموعة",
                    search: "بحث:",
					paginate: {
						first:      "الأول",
						last:       "الأخير",
						next:       "التالي",
						previous:   "السابق"
					},					
                    zeroRecords: "بل يوجد نتائج مطابقة للبحث"
                },
                scrollY: 300,
                deferRender: true,
                scroller: !0,
                stateSave: !0,
                order: [
                    [0, "asc"]
                ],
                lengthMenu: [
                    [10, 15, 20, -1],
                    [10, 15, 20, "الكل"]
                ],
				bAutoWidth: false,
                scrollX: false,
                scrollCollapse: false,				
				paging: true,
                pageLength: 10,
                
            })
        },
        t = function() {
            var e = $("#tabelser");	
            e.dataTable({
                language: {
                    aria: {
                        sortAscending: ": activate to sort column ascending",
                        sortDescending: ": activate to sort column descending"
                    },
                    emptyTable: "لا يوجد سلاسل",
                    info: "عرض _START_ إلى _END_ من _TOTAL_ سلسلة",
                    infoEmpty: "لم يتم العثور على إي سلسلة مطابقة",
                    infoFiltered: "(مادة مختارة من _MAX_ كعدد كلي)",
                    lengthMenu: "_MENU_ سلسلة",
                    search: "بحث:",
					paginate: {
						first:      "الأول",
						last:       "الأخير",
						next:       "التالي",
						previous:   "السابق"
					},					
                    zeroRecords: "بل يوجد نتائج مطابقة للبحث"
                },
                scrollY: 300,
                deferRender: true,
                scroller: !0,
                stateSave: !0,
                order: [
                    [0, "asc"]
                ],
                lengthMenu: [
                    [10, 15, 20, -1],
                    [10, 15, 20, "الكل"]
                ],
				bAutoWidth: false,
                scrollX: false,
                scrollCollapse: false,				
				paging: true,
                pageLength: 10,
                
            })
        },
        n = function() {
            var e = $("#tabelkht");	
            e.dataTable({
                language: {
                    aria: {
                        sortAscending: ": activate to sort column ascending",
                        sortDescending: ": activate to sort column descending"
                    },
                    emptyTable: "لا يوجد مواد",
                    info: "عرض _START_ إلى _END_ من _TOTAL_ مادة",
                    infoEmpty: "لم يتم العثور على إي مادة مطابقة",
                    infoFiltered: "(مادة مختارة من _MAX_ كعدد كلي)",
                    lengthMenu: "_MENU_ مادة",
                    search: "بحث:",
					paginate: {
						first:      "الأول",
						last:       "الأخير",
						next:       "التالي",
						previous:   "السابق"
					},					
                    zeroRecords: "بل يوجد نتائج مطابقة للبحث"
                },
                scrollY: 300,
                deferRender: true,
                scroller: !0,
                stateSave: !0,
                order: [
                    [0, "asc"]
                ],
                lengthMenu: [
                    [10, 15, 20, -1],
                    [10, 15, 20, "الكل"]
                ],
				bAutoWidth: false,
                scrollX: false,
                scrollCollapse: false,				
				paging: true,
                pageLength: 10,
                
            })
        };

    return {
        init: function() {
            jQuery().dataTable && (e(), t(), n())
        }
    }
}();
jQuery(document).ready(function() {
    TableDatatablesScroller.init()
});