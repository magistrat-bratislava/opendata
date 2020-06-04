function showData( file ) 
{
	$.ajax({
		type: "GET",
		url: file,
		dataType: "text",
		success: function(data) {processData(data);}
		});
}

function encode_utf8(s) {
  return unescape(encodeURIComponent(s));
}

function processData( allText ) 
{
	var allTextLines = allText.split(/\r\n|\n/);
	var headers = allTextLines[0].split(',');

	var lines = [];
	for (var i=1; i<allTextLines.length; i++) 
	{
		var data = allTextLines[i].split(',');
		if (data.length == headers.length) {
			var tarr = [];

			for (var j=0; j<headers.length; j++) {
				tarr.push(headers[j]+":"+data[j]);
			}

			lines.push(tarr);
		}
	}
	alert(encode_utf8(lines));
}

jQuery(document).ready(function () {

	if ($(window).scrollTop() > 300)
		$(".scroll-top").show();

	$(window).scroll(function (event) {
		var scroll = $(window).scrollTop();
		if (scroll > 300) {
			$(".scroll-top").fadeIn();
		} else {
			$(".scroll-top").fadeOut();
		}
	});

	$(".scroll-top").click(function () {
		$("html, body").animate({ scrollTop: 0 }, "slow");
	});


	$('#show-next').click(function () {
		var order = $("#order").val();

		$.ajax({
			url: next_url,
			method: 'POST',
			cache: false,
			data: "cat="+category+"&page="+actual_page+"&order="+order,
		}).done(function (data) {
			if (data) {
				if (data == 'invalid') {
					alert("Server is not responding!");
				} else {
					actual_page = actual_page + 1;

					if (actual_page >= max_pages)
						$("#show-next").parent().remove();

					$("#datasets").append(data);
				}
			} else {
				alert("Server is not responding!");
			}

		}).fail(function (jQueryXhr, textStatus) {
			alert("Server is not responding!");
		});
	});


	$('#show-next-search').click(function () {
		$.ajax({
			url: next_url,
			method: 'POST',
			cache: false,
			data: "search="+search+"&page="+actual_page,
		}).done(function (data) {
			if (data) {
				if (data == 'invalid') {
					alert("Server is not responding!");
				} else {
					actual_page = actual_page + 1;

					if (actual_page >= max_search)
						$("#show-next-search").parent().remove();

					$("#datasets").append(data);
				}
			} else {
				alert("Server is not responding!");
			}

		}).fail(function (jQueryXhr, textStatus) {
			alert("Server is not responding!");
		});
	});

    $("#search-ex").bind("keyup", function() {
		if($(this).val().length>2) {
			extendedSearch();
		}
	});

    $("#uniq_id").bind("keyup", function() {
		if($(this).val().length>2) {
			extendedSearch();
		}
	});

    $("#category").change(function () {extendedSearch();});
    $("#year").change(function () {extendedSearch();});
    $("#district").change(function () {extendedSearch();});
    $("#authors").change(function () {extendedSearch();});
    $("#visualization").change(function () {extendedSearch();});

	$('#show-next-search-extend').click(function () {
		$.ajax({
			url: next_url,
			method: 'POST',
			cache: false,
			data: $("#frm-searchExtendedForm").serialize() + "&page="+actual_page+"&submit=1",
		}).done(function (data) {
			if (data) {
				if (data == 'invalid') {
					alert("Server is not responding!");
				} else {
					actual_page = actual_page + 1;

					if (actual_page >= max_search)
						$(".pagination").hide();
					else
						$(".pagination").show();

					$("#extendedResults").append(data);
				}
			} else {
				alert("Server is not responding!");
			}

		}).fail(function (jQueryXhr, textStatus) {
			alert("Server is not responding!");
		});
	});


	$("#order").change(function () {
		$("#frm-orderForm").submit();
	});
 /*
    //this code is for the gmap
    var map = new GMaps({
        el: '#map',
        lat: -12.043333,
        lng: -77.028333
    });
 */

 
 /*
    //this code is for smooth scroll and nav selector
    $(document).ready(function () {
        $(document).on("scroll", onScroll);

        //smoothscroll
        $('a[href^="#"]').on('click', function (e) {
            e.preventDefault();
            $(document).off("scroll");

            $('a').each(function () {
                $(this).removeClass('active');
            })
            $(this).addClass('active');

            var target = this.hash,
                menu = target;
            $target = $(target);
            $('html, body').stop().animate({
                'scrollTop': $target.offset().top + 2
            }, 500, 'swing', function () {
                window.location.hash = target;
                $(document).on("scroll", onScroll);
            });
        });
    });

    function onScroll(event) {
        var scrollPos = $(document).scrollTop();
        $('.navbar-default .navbar-nav>li>a').each(function () {
            var currLink = $(this);
            var refElement = $(currLink.attr("href"));
            if (refElement.position().top <= scrollPos && refElement.position().top + refElement.height() > scrollPos) {
                $('.navbar-default .navbar-nav>li>a').removeClass("active");
                currLink.addClass("active");
            } else {
                currLink.removeClass("active");
            }
        });
    }


    //this code is for animation nav
    jQuery(window).scroll(function () {
        var windowScrollPosTop = jQuery(window).scrollTop();

        if (windowScrollPosTop >= 150) {
            jQuery(".header").css({
                "background": "#B193DD",
            });
            jQuery(".top-header img.logo").css({
                "margin-top": "-40px",
                "margin-bottom": "0"
            });
            jQuery(".navbar-default").css({
                "margin-top": "-15px",
            });
        } else {
            jQuery(".header").css({
                "background": "transparent",
            });
            jQuery(".top-header img.logo").css({
                "margin-top": "-15px",
                "margin-bottom": "25px"
            });
            jQuery(".navbar-default").css({
                "margin-top": "12px",
                "margin-bottom": "0"
            });

        }
    });
*/



});

function extendedSearch()
{
	$.ajax({
		url: extended_url,
		method: 'POST',
		cache: false,
		data: $("#frm-searchExtendedForm").serialize() + "&page=0&submit=1",
	}).done(function (data) {
		if (data) {
			if (data == 'invalid') {
				alert("Server is not responding!");
			} else {

				$("#extendedResults").html(data);

				actual_page = actual_page + 1;

				if (actual_page >= max_search)
					$(".pagination").hide();
				else
					$(".pagination").show();
			}
		} else {
			alert("Server is not responding!");
		}

	}).fail(function (jQueryXhr, textStatus) {
		alert("Server is not responding!");
	});
}


/*var mymap = L.map('map').setView([48.14816, 17.10674], 13);

L.tileLayer('https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw', {
	maxZoom: 18,
	attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, ' +
		'<a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
		'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
	id: 'mapbox.streets'
}).addTo(mymap);*/
