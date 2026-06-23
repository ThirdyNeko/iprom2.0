$(document).ready(function () {
  table = $("#LOAtable").DataTable({
    processing: true,
    serverSide: true,
    pageLength: 25,
    responsive: true,
    dom: "lrtip",
    ordering: false,

    ajax: {
      url: "functions/fetch_loa.php",
      type: "POST",
      data: function (d) {
        d.name = $("#filterName").val();
      },
    },

    columns: [
      { data: "promodiser" },
      { data: "agency" },
      { data: "employment_status" },
      { data: "sub_status" },
      { data: "effectivity_date" },
    ],
  });

  $("#filterName").on("input", function () {
    table.draw();
  });
});
