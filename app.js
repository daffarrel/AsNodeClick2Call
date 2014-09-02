var express = require("express");
var path = require('path');
var http = require('http');
var bodyParser = require("body-parser");

// prepare a simple HTTP server
var app = express();
var server = http.createServer(app);

var as_tools = require('./astools.js');
var logger = require('log4js').getLogger('App.main');

var SECRET_KEY = "dfalkehasdhf2349238dfskhfk2";
var PORT = 3000;
server.listen(PORT);
logger.info("Server started at port " + PORT);

app.use(bodyParser.urlencoded({ extended: false}));
//app.use(bodyParser.json());

app.all('*', function(req, res, next) {
  res.header("Access-Control-Allow-Origin", "*");
  res.header('Access-Control-Allow-Methods', 'GET,PUT,POST,DELETE');
  next();
});

app.get('/', function(req, res){
	res.send('Hello world!');
});


/*
 Payload:
  to_number*: nnnnnnnnnn (PSTN number)
  connect_extn*: nnnn  (SIP extention number)
  record_call*: Y|N
  message: (Text message to be sent during the callout) (optional parameter)
  tripid: (Tripid to record the transaction and the call) (optional parameter)
 */
app.post('/click2call', function(req, res){
  res.statusCode = 200;
  var res_message = "Your request will be processed now!";
  var res_status = "Success";

  // data init
  var to_number = "";
  var connect_extn = "";
  var record_call = 'N';
  var message = "";
  var tripid = 0;

  if (!req.headers.hasOwnProperty("x-api-key") || req.headers["x-api-key"] != SECRET_KEY){
    res.statusCode = 403;
    res_message = "You can not access to this area!";
    res_status = "Forbidden";
  }

  if (!req.body.hasOwnProperty("to_number")) {
    res.statusCode = 400;
    res_message = "to_number is required!";
    res_status = "Error";
  }
  else if (!req.body.hasOwnProperty("connect_extn")) {
    res.statusCode = 400;
    res_message = "connect_extn is required!";
    res_status = "Error";
  }
  else if (!req.body.hasOwnProperty("record_call")) {
    res.statusCode = 400;
    res_message = "record_call is required!";
    res_status = "Error";
  }
  else{
   if ( !as_tools.makeNewCall(req.body) ){
     res.statusCode = 501;
     res_message = "Server have some problems and can not handle this call now!";
     res_status = "Failed";
     logger.debug("Failed to originate call with AMI");
    }
  }

  res.jsonp(JSON.stringify({ status: res_status, message: res_message }));
  res.end();
});

//var hasOwnProperty = Object.prototype.hasOwnProperty;
