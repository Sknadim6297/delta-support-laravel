@extends('admin.layouts.app')
@section('content')
         <main>

    
           
           

    <div>

        <!-- TITLE -->
        


        <!-- SEARCH AREA -->
        <div>

            <!-- MEMBER ID -->
            <div>
                <label>Member ID</label>

                <input type="text"
                     
                      
                       placeholder="Enter Member ID">
            </div>


            <!-- FROM DATE -->
            <div>
                <label>From Date</label>

                <input type="date"
                      
                      >
            </div>


            <!-- TO DATE -->
            <div>
                <label>To Date</label>

                <input type="date"
                       
                      >
            </div>


            <!-- TOTAL AMOUNT -->
          

        </div>


        <!-- BUTTONS -->
        <div>

            <button type="button"
                   
                    ">

                <i></i>
                Export to Excel

            </button>


            <button type="button"
                   
                    onclick="searchInvestment()">

                <i></i>
                Search

            </button>


            <button type="button"
                   
                   ">

                <i></i>
                Reset

            </button>

        </div>

          <div>

                <div>

                    <span>Total Amount (USDT)</span>

                    <strong>160838.6922</strong>

                </div>

            </div>
        <!-- TABLE -->
        <div>

    <table>

        <thead>
            <tr>
                <th>Serial No</th>
                <th>Category</th>
                <th>Ticket ID</th>
                <th>Member ID</th>
                <th>Member Name</th>
                <th>Close Date</th>
                <th>Replied Message</th>
                <th>Message</th>
            </tr>
        </thead>

        <tbody>

            <tr>
                <td>1</td>
                <td>Payment</td>
                <td>TKT10001</td>
                <td>MB10001</td>
                <td>Rahul Das</td>
                <td>20-Aug-2026</td>
                <td>Payment issue has been resolved.</td>
                <td>Payment related issue</td>
            </tr>

            <tr>
                <td>2</td>
                <td>Wallet</td>
                <td>TKT10002</td>
                <td>MB10002</td>
                <td>Sumit Ghosh</td>
                <td>21-Aug-2026</td>
                <td>Wallet balance has been updated.</td>
                <td>Wallet balance not updated</td>
            </tr>

            <tr>
                <td>3</td>
                <td>Profile</td>
                <td>TKT10003</td>
                <td>MB10003</td>
                <td>Priya Das</td>
                <td>22-Aug-2026</td>
                <td>Your profile has been updated successfully.</td>
                <td>Unable to update profile</td>
            </tr>

        </tbody>

    </table>

</div>


        <!-- PAGINATION -->
        <div>

            <div>
                Showing 1 to 3 of 3 entries
            </div>

            <nav>

                <ul>

                    <li>
                        <a href="#">Previous</a>
                    </li>

                    <li>
                        <a href="#">1</a>
                    </li>

                    <li>
                        <a href="#">Next</a>
                    </li>

                </ul>

            </nav>

        </div>

    </div>


    

    


       </main>
       @endsection